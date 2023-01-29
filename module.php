<?php
/**
 * Online Module Management Platform
 * 
 * Main file for example module
 * Contains the required function to allow the module to work
 * 
 * @author  The OMMP Team
 * @version 1.0
 */

/**
 * Check a configuration value
 * 
 * @param string $name
 *      The configuration name (without the module name)
 * @param string $value
 *      The configuration value
 * @param Lang $lang
 *         The Lang object for the current module
 * 
 * @return boolean|string
 *      TRUE is the value is correct for the given name
 *      else a string explaination of the error
 */
function shorturl_check_config($name, $value, $lang) {
    
	// Check not empty
	if ($name == "characters" && $value == "") {
		return $lang->get("list_cant_be_empty");
	}

	// Check number
	if ($name == "length") {
		$value = intval($value);
		if ($value <= 0) {
			return $lang->get("must_be_positive");
		}
	}

	// If everything is good return TRUE
	return TRUE;
	
}

/**
 * Handle user deletion calls
 * This function will be called by the plateform when a user is deleted,
 * it must delete all the data relative to the user
 * 
 * @param int $id
 *         The id of the user that will be deleted
 */
function shorturl_delete_user($id) {
	global $sql, $db_prefix;
	// Delete the links and the visits
	$sql->exec("DELETE shorturl, shorturl_visits FROM {$db_prefix}shorturl shorturl LEFT JOIN {$db_prefix}shorturl_visits shorturl_visits ON id = link_id WHERE `owner` = " . $sql->quote($id));
}

/**
 * Handle an API call
 * 
 * @param string $action
 *      The name of the action to process
 * @param array $data
 *      The data given with the action
 * 
 * @return array|boolean
 *      An array containing the data to respond
 *      FALSE if the action does not exists
 */
function shorturl_process_api($action, $data) {
	global $user, $config, $db_prefix, $sql;
    
	// Manage actions

	if ($action == "shorten-link") {

		// Check the parameters
		if (!check_keys($data, ["url"])) {
			return ["error" => $user->module_lang->get("missing_parameter")];
		}

		// Check the URL
		if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
			return ["error" => $user->module_lang->get("invalid_url")];
		}

		// Get forbidden names
		$reserved = explode(",", $config->get("shorturl.reserved"));

		// Generate an id
		$identifier = "";
		$i = 0;
		do {
			// Try 10 times to generate an identifier bedore returning an error
			$i += 1;
			if ($i > 10) {
				return ["error" => $user->module_lang->get("failed_to_generate_id")];
			}
			// Random string
			$identifier = random_str($config->get("shorturl.length"), $config->get("shorturl.characters"));
		} while (in_array($identifier, $reserved) || dbSearchValue("{$db_prefix}shorturl", "identifier", $identifier));

		// Save the link
		$time = time();
		$result = $sql->exec("INSERT INTO {$db_prefix}shorturl VALUE (NULL, " . $sql->quote($identifier) . ", " . $sql->quote($user->id) . ", " . $sql->quote($data['url']) . ", $time, $time)");

		// Check for errors
		if ($result === FALSE) {
            return ["error" => $user->module_lang->get("cannot_save_link")];
        }

		// Return the created link
		$formatted_date = date($user->module_lang->get("date_format"), $time);
		return [
			"ok" => TRUE,
			"link" => [
				"id" => $sql->lastInsertId(),
				"identifier" => $identifier,
				"target" => $data['url'],
				"creation" => $time,
				"formatted_creation" => $formatted_date,
				"last_edit" => $time,
				"formatted_last_edit" => $formatted_date,
				"my_link" => TRUE
			]
		];

	} else if ($action == "get-my-links") {

		// Check if user has the right to see his links
		if (!$user->has_right("shorturl.see_list")) {
			return ["error" => $user->module_lang->get("list_missing_right")];
		}

		// Get the start
		$start = 0;
		if (isset($data['start'])) {
			$start = intval($data['start']);
		}

		// Get all the links
		$links = [];
		$request = $sql->query("SELECT * FROM {$db_prefix}shorturl WHERE `owner` = " . $sql->quote($user->id) . " ORDER BY edit_ts DESC LIMIT $start, 10");
		while ($link = $request->fetch()) {
			$links[] = [
				"id" => $link['id'],
				"identifier" => $link['identifier'],
				"target" => $link['target'],
				"creation" => $link['creation_ts'],
				"formatted_creation" => date($user->module_lang->get("date_format"), $link['creation_ts']),
				"last_edit" => $link['edit_ts'],
				"formatted_last_edit" => date($user->module_lang->get("date_format"), $link['edit_ts']),
				"my_link" => TRUE
			];
		}
		$request->closeCursor();

		// Return the links
		return [
			"ok" => TRUE,
			"links" => $links,
			"total" => dbCount("{$db_prefix}shorturl", "`owner` = " . $sql->quote($user->id))
		];

	} else if ($action == "delete-link") {

		// Check the parameters
		if (!check_keys($data, ["id"])) {
			return ["error" => $user->module_lang->get("missing_parameter")];
		}

		// Get informations about the link
		$link = dbGetFirstLineSimple("{$db_prefix}shorturl", "id = " . $sql->quote($data['id']));
		
		// Check if link exists
		if ($link === FALSE) {
			return ["error" => $user->module_lang->get("link_does_not_exists")];
		}

		// Check if user has right to delete the link
		if ($link['owner'] != $user->id && !$user->has_right("shorturl.delete_any")) {
			return ["error" => $user->module_lang->get("no_right_to_delete")];
		}

		// Delete the link
		$result = $sql->exec("DELETE shorturl, shorturl_visits FROM {$db_prefix}shorturl shorturl LEFT JOIN {$db_prefix}shorturl_visits shorturl_visits ON id = link_id WHERE id = " . $sql->quote($data['id']));

		// Check for errors
		if ($result === FALSE) {
            return ["error" => $user->module_lang->get("cannot_delete_link")];
        }

		// Return success
		return ["ok" => TRUE];

	} else if ($action == "edit-link") {

		// Check the parameters
		if (!check_keys($data, ["id", "url"])) {
			return ["error" => $user->module_lang->get("missing_parameter")];
		}

		// Check the URL
		if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
			return ["error" => $user->module_lang->get("invalid_url")];
		}

		// Get informations about the link
		$link = dbGetFirstLineSimple("{$db_prefix}shorturl", "id = " . $sql->quote($data['id']));
		
		// Check if link exists
		if ($link === FALSE) {
			return ["error" => $user->module_lang->get("link_does_not_exists")];
		}

		// Check if user has right to edit the link
		if (($link['owner'] == $user->id && (!$user->has_right("shorturl.edit") && !$user->has_right("shorturl.edit_any"))) || // Owner without the right edit or edit_any
			($link['owner'] != $user->id && !$user->has_right("shorturl.edit_any"))) { // Not owner without the right edit_any
			return ["error" => $user->module_lang->get("no_right_to_edit")];
		}

		// Update the link
		$result = $sql->exec("UPDATE {$db_prefix}shorturl SET `target` = " . $sql->quote($data['url']) . " WHERE id = " . $sql->quote($data['id']));

		// Check for errors
		if ($result === FALSE) {
            return ["error" => $user->module_lang->get("cannot_delete_link")];
        }

		// Return success
		return ["ok" => TRUE];

	}

    return FALSE;
}

/**
 * Handle page loading for the module
 * 
 * @param string $page
 *      The page requested in the module
 * @param string $pages_path
 *      The absolute path where the pages are stored for this module
 * 
 * @return array|boolean
 *      An array containing multiple informations about the page as described below
 *      [
 *          "content" => The content of the page,
 *          "title" => The title of the page,
 *          "og_image" => The Open Graph image (optional),
 *          "description" => A description of the web page
 *      ]
 *      FALSE to generate a 404 error
 */
function shorturl_process_page($page, $pages_path) {
    global $user;
    // This module uses only the HTML files without processing them
    return module_simple_html($page, $pages_path, [], [
		"" => $user->module_lang->get("shorten_link"),
		"my-links" => $user->module_lang->get("my_links"),
		"all-links" => $user->module_lang->get("all_links"),
		"statistics" => $user->module_lang->get("statistics")
    ]);
}

/**
 * Handle the special URL pages
 * 
 * @param string $url
 *      The url to check for a special page
 * 
 * @return boolean
 *      TRUE if this module can process this url (in this case this function will manage the whole page display)
 *      FALSE else (in this case, we will check the url with the remaining modules, order is defined by module's priority value)
 */
function shorturl_url_handler($url) {
	global $db_prefix, $sql;
    
	// Search the value in the database
	$link = dbGetFirstLineSimple("{$db_prefix}shorturl", "identifier = " . $sql->quote($url), "id, target");

	// If link does not exists, return FALSE
	if ($link === FALSE) {
		return FALSE;
	}

	// Save the visit
	$sql->exec("INSERT INTO {$db_prefix}shorturl_visits VALUES (" . $sql->quote($link['id']) . ", " . $sql->quote($_SERVER['REMOTE_ADDR']) . ", " . time() .
		", " . $sql->quote(substr($_SERVER['HTTP_USER_AGENT'], 0, 256)) . ", " . $sql->quote(substr($_SERVER['HTTP_REFERER'], 0, 256)) . ")");

	// Redirect to the page
	header('Location: ' . $link['target'], true, 302);

	// Return success
	return TRUE;
}