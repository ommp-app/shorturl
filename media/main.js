/**
 * Check if ENTER has been pressed in an input
 * 
 * @param {*} callback The function to call if enter was pressed
 */
function checkEnter(callback) {
	if (event.key === 'Enter') {
		callback();
	}
}

/**
 * Return a link displayed as a button
 * 
 * @param {*} target The target of the link(relative to the module)
 * @param {*} content The text of the button
 */
function getLinkButton(target, content) {
	return '<a href="' + ommp_dir + 'shorturl/' + escapeHtml(target) + '" class="btn btn-outline-dark btn-lg ms-2 me-2" role="button" aria-pressed="true">' + escapeHtml(content) + '</a>'
}

/**
 * Display a link in a given element
 * 
 * @param {*} element The id of the container element
 * @param {*} link An object representing the link as returned by the API
 * @param {*} stats Should we display the statistictd button
 * @param {*} remove Should we display the delete button
 * @param {*} editable Is the link editable?
 */
 function displayLink(element, link, stats, remove, editable) {
	// Copy button is only enabled in HTTPS
	$('#' + element).append('<div id="link-preview-' + link.id + '"><input class="form-control mb-1 mt-4 me-2" style="width:50%;display:inline-block;" type="text" id="short-' + link.id + '" value="{JS:S:SCHEME}://{JS:S:DOMAIN}{JS:S:DIR}' + escapeHtml(link.identifier) + '" onclick="this.setSelectionRange(0,this.value.length)" readonly />' +
	('{JS:S:SCHEME}' == 'https' ? '<img class="inline-image-semi ms-2 me-2" role="button" aria-pressed="true" title="{JS:L:COPY}" src="{JS:S:DIR}media/shorturl/copy.svg" onclick="navigator.clipboard.writeText(\'{JS:S:SCHEME}://{JS:S:DOMAIN}{JS:S:DIR}' + escapeHtml(link.identifier) + '\')" />' : '') +
	(stats ? '<img class="inline-image-semi ms-2 me-2" role="button" aria-pressed="true" title="{JS:L:STATISTICS}" src="{JS:S:DIR}media/shorturl/stats.svg" onclick="location.href=\'{JS:S:DIR}shorturl/statistics#' + escapeHtml(link.identifier) + '\'" />' : '') +
	(editable ? '<img class="inline-image-semi ms-2 me-2" role="button" aria-pressed="true" title="{JS:L:EDIT}" src="{JS:S:DIR}media/ommp/images/edit.svg" onclick="$(\'#edit-link-' + link.id + '\').toggle(500)" />' : '') +
	(remove ? '<img class="inline-image-semi ms-2 me-2" role="button" aria-pressed="true" title="{JS:L:DELETE}" src="{JS:S:DIR}media/shorturl/delete.svg" onclick="deleteLink(' + link.id + ')" />' : '') +
	'<br /><img class="inline-image-small ms-2 me-2" role="button" aria-pressed="true" title="{JS:L:TARGET}" src="{JS:S:DIR}media/shorturl/target.svg" />' +
	'<a href="' + escapeHtml(link.target) + '" target="_blank" id="link-a-' + link.id + '">' + escapeHtml(link.target) + '</a>' +
	'<br /><div id="edit-link-' + link.id + '" style="display:none;"><input class="form-control me-2 mt-1 mb-1" style="width:70%;display:inline-block;" type="text" id="link-input-' + link.id + '" value="' + escapeHtml(link.target) + '" onkeyup="checkEnter(()=>{editLink(' + link.id +', this.value)})">' +
	'<div class="btn pt-1 pb-1 ms-2 me-2 btn-light" style="vertical-align:baseline;" role="button" aria-pressed="true" onclick="editLink(' + link.id +', $(\'#link-input-' + link.id + '\').val())">{JS:L:SAVE}</div></div>' +
	'<span>{JS:L:CREATION}' + link.formatted_creation + ' - {JS:L:LAST_EDIT}<span id="last-edit-' + link.id + '">' + escapeHtml(link.formatted_last_edit) + '</span></span></div>');
}

/**
 * Delete a link after displaying a confirmation
 * 
 * @param {*} id The id of the link
 */
function deleteLink(id) {
	promptChoice('{JS:L:DELETE_LINK_CONFIRM}', '{JS:L:YES}', '{JS:L:NO}', () => {
		// Call the API
		Api.apiRequest('shorturl', 'delete-link', {'id': id}, r => {
			// Check for errors
			if (typeof r.error !== 'undefined') {
				notifError(r.error, '{JS:L:ERROR}');
				return;
			}
			// Display message and delete from page
			notif('{JS:L:LINK_DELETED}');
			$('#link-preview-' + id).remove();
			// If we are in a list, decrement the position
			if (typeof currentPos !== 'undefined') {
				currentPos--;
			}
		});
	}, () => {}, '{JS:L:WARNING}');
}

/**
 * Edit the target of a link
 * 
 * @param {*} id The id of the link
 * @param {*} id The new target of the link
 */
function editLink(id, target) {
	// Call the API
	Api.apiRequest('shorturl', 'edit-link', {'id': id, 'url': target}, r => {
		// Check for errors
		if (typeof r.error !== 'undefined') {
			notifError(r.error, '{JS:L:ERROR}');
			return;
		}
		// Update the link and hide the editor
		$('#link-a-' + id).html(escapeHtml(target)).attr('href', target);
		$('#last-edit-' + id).html(escapeHtml(r.formatted_last_edit));
		$('#edit-link-' + id).hide(500);
		notif('{JS:L:LINK_UPDATED}');
	});
}