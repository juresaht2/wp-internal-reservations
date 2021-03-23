(function($) {

	"use strict";

	var options = {
		events_source: wordpress.ajax_url,
		view: 'month',
		tmpl_path: wordpress.tmpl_url,
		tmpl_cache: false,
		language: 'sl-SL',
		day: 'now',
		onAfterViewLoad: function(view) {
			$('#wpir-calendar-title').text(this.getTitle());
			$('.btn-group button').removeClass('active');
			$('button[data-calendar-view="' + view + '"]').addClass('active');
		},
		classes: {
			months: {
				general: 'label'
			}
		}
	};

	var calendar = $('#wpir-calendar').calendar(options);

	window.wpir_edit_box = function(e) {
		$('#wpir-calendar-overlay').show();
		alert(e.data.id);
		/* TODO: this should fill the details of an edited or blank entry */
	}

	/* TODO: https://stackoverflow.com/a/6960586/2897386 */

	$('.btn-group button[data-calendar-edit]').each(function() {
		var $this = $(this);
		$this.click({id: 0}, window.wpir_edit_box);
	});

  $('#wpir-calendar-overlay').on('click', function() {
    if($(event.target).is('#wpir-calendar-overlay')) {
      $('#wpir-calendar-overlay').hide();
    }
  });	

	$('.btn-group button[data-calendar-nav]').each(function() {
		var $this = $(this);
		$this.click(function() {
			calendar.navigate($this.data('calendar-nav'));
		});
	});

	$('.btn-group button[data-calendar-view]').each(function() {
		var $this = $(this);
		$this.click(function() {
			calendar.view($this.data('calendar-view'));
		});
	});

}(jQuery));