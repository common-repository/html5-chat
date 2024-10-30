(function() {
    tinymce.create('tinymce.plugins.button_html5_chat', {
        init : function(ed, url) {
            var image = url+'/icon-btn-editor.png';

            ed.addButton('btn_html5_chat', {
                title : 'Insert HTML5 chat',
                cmd : 'insert_html5_chat',
                class: 'html5_button_editor',
                image : image
            });

            ed.addCommand('insert_html5_chat', function() {
                var selected_text = ed.selection.getContent();
                ed.execCommand('mceInsertContent', 0, '[HTML5CHAT width=100% height=640px]');
            });
        },

        createControl : function(n, cm) {
            return null;
        }
    });

    tinymce.PluginManager.add('button_html5_chat', tinymce.plugins.button_html5_chat);
})();