/** Native ProcessWire tabs scoped to this Action's language message fieldsets. */
(function ($) {
	'use strict';
	$(function () {
		$('.gpt-live-message-tabs').each(function () {
			const content = $(this).children('.InputfieldContent');
			const pages = content.children('.Inputfields').children('.gpt-live-language-page');
			if(!pages.length || !$.fn.WireTabs) return;
			pages.each(function () {
				const header = $(this).children('.InputfieldHeader');
				// WireTabs inserts titles as HTML, so encode the language's plain label.
				$(this).attr('title', $('<span>').text(header.text().trim()).html());
				header.hide();
			});
			content.WireTabs({
				items: pages,
				id: this.id + '_language_tabs',
				rememberTabs: -1
			});
		});
	});
}(jQuery));
