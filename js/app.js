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
		$.ajax({
			type: "POST",
			url: wordpress.ajax_url,
			data: {'action': 'wpir_edit_get', 'data': e.data},
			success: function(result)
			{
				$('#wpir-calendar-edit-box input[name="id"]').val(result.id);
				$('#wpir-calendar-edit-box input[name="title"]').val(result.title);
				$('#wpir-calendar-edit-box input[name="from"]').val(result.from);
				$('#wpir-calendar-edit-box input[name="until"]').val(result.until);
				$('#wpir-calendar-edit-box input[name="from"]').attr("max", result.until);
				$('#wpir-calendar-edit-box input[name="until"]').attr("min", result.from);
				$('#wpir-calendar-submit').prop('disabled', !result.editable);
				$('#wpir-calendar-overlay').show();
			}
		});
	}

	$("#wpir-calendar-edit-box form").submit(function(e) {

		e.preventDefault();

		$.ajax({
			type: "POST",
			url: wordpress.ajax_url,
			data: {'action': 'wpir_edit_set', 'data': $(this).serializeArray()},
			success: function(result)
			{
				if(result.overlap) {
					alert("V tem terminu že obstaja rezervacija!");
				} else {
					calendar.view(); //refresh
					$('#wpir-calendar-overlay').hide();
				}
			}
		});
	});	

	$('.btn-group button[data-calendar-edit]').each(function() {
		var $this = $(this);
		$this.click({id: 0}, window.wpir_edit_box);
	});

  $('#wpir-calendar-overlay').on('click', function() {
    if($(event.target).is('#wpir-calendar-overlay')) {
      $('#wpir-calendar-overlay').hide();
    }
  });

	$('#wpir-calendar-edit-box input[name="from"]').change(function() {
		$('#wpir-calendar-edit-box input[name="until"]').attr("min", $(this).val());
	});

	$('#wpir-calendar-edit-box input[name="until"]').change(function() {
		$('#wpir-calendar-edit-box input[name="from"]').attr("max", $(this).val());
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