window.PP_JS = {

	editor_instance : null,

	render_editor : function( editor_id, editor_field_id ) {

		var editor = ace.edit( editor_id, editor_field_id );
		editor.setTheme("ace/theme/twilight");
		editor.getSession().setMode("ace/mode/javascript");

		var pp_js_input = jQuery("#"+ editor_field_id);
		editor.setValue( PP_JS.decode_html( pp_js_input.val() ) );

		editor.getSession().on("change", function(e) {
			pp_js_input.val( PP_JS.encode_html( editor.getValue() ) );
		});

		PP_JS.editor_instance = editor;

	},

	encode_html : function( s ) {

		return jQuery("<div/>").text(s).html();

	},

	decode_html : function( s ) {

		return jQuery("<div/>").html(s).text();

	}

};