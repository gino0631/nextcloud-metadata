(function() {
    var MetadataTabView = OCA.Files.DetailTabView.extend({
        id: 'metadataTabView',
        className: 'tab metadataTabView',

        getLabel: function() {
            return t('metadata', 'Metadata');
        },

        render: function() {
            this.$el.html('<div style="text-align:center; word-wrap:break-word;" class="get-metadata"><p><img src="'
                + OC.imagePath('core','loading.gif')
                + '"><br><br></p><p>'
                + t('metadata', 'Reading metadata ...')
                + '</p></div>');

            var url = OC.generateUrl('/apps/metadata/get'),
                data = {source: this.getFileInfo().getFullPath()},
                _self = this;
            $.ajax({
                type: 'GET',
                url: url,
                dataType: 'json',
                data: data,
                async: true,
                success: function(data) {
                    _self.updateDisplay(data);
                }
            });
        },

        canDisplay: function(fileInfo) {
            if (!fileInfo || fileInfo.isDirectory() || !fileInfo.has('mimetype')) {
                return false;
            }
            var mimetype = fileInfo.get('mimetype');

            return (['image/jpeg', 'image/tiff'].indexOf(mimetype) > -1);
        },

        updateDisplay: function(data) {
            var html = '';

            if (data.response === 'success') {
                html += '<table>';

                var metadata = data.metadata;
                for (m in metadata) {
                    html += '<tr><td class="key">' + m + ':</td><td class="value">' + metadata[m] + '</td></tr>';
                }

                html += '</table>';

            } else {
                html = data.msg;
            }

            this.$el.find('.get-metadata').html(html);
        },
    });

    OCA.Metadata = OCA.Metadata || {};

    OCA.Metadata.MetadataTabView = MetadataTabView;
})();
