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
	$sql->exec("DELETE shorturl, shorturl_visits FROM {$db_prefix}shorturl shorturl JOIN {$db_prefix}shorturl_visits shorturl_visits ON id = link_id WHERE `owner` = " . $sql->quote($id));
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
				"edit" => $time,
				"formatted_last_edit" => $formatted_date,
				"owner" => $user->username
			]
		];

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
    // TODO
    return FALSE;
}