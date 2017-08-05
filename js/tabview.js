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

            return (['audio/mpeg', 'image/jpeg', 'image/tiff'].indexOf(mimetype) > -1);
        },

        updateDisplay: function(data) {
            var html = '';
            var showLocation = false;

            if (data.response === 'success') {
                html += '<table>';

                var metadata = data.metadata;
                for (m in metadata) {
                    html += '<tr><td class="key">' + m + ':</td><td class="value">' + metadata[m] + '</td></tr>';
                }

                showLocation = (data.lat != null) && (data.lon != null);
                if (showLocation) {
                    var url = 'https://nominatim.openstreetmap.org/reverse',
                        data = {lat: data.lat, lon: data.lon, format: 'json', zoom: 18},
                        _self = this;
                    $.ajax({
                        type: 'GET',
                        url: url,
                        dataType: 'json',
                        data: data,
                        async: true,
                        success: function(data) {
                            _self.updateLocation(data);
                        }
                    });

                    html += '<tr><td class="key">' + t('metadata', 'Location') + ':</td><td class="value"><a href="#" class="get-location">' + t('metadata', 'Resolving, click here to view on map ...') + '</a></td></tr>';
                }

                html += '</table>';

            } else {
                html = data.msg;
            }

            this.$el.find('.get-metadata').html(html);

            if (showLocation) {
                this.$el.find('.get-location')
                    .click(function() {
                        var bbox = [data.lon - 0.0051, data.lat - 0.0051, data.lon - -0.0051, data.lat - -0.0051];

                        var iframe = document.createElement('iframe');
                        iframe.setAttribute('width', '100%');
                        iframe.setAttribute('height', '100%');
                        iframe.setAttribute('src', 'https://www.openstreetmap.org/export/embed.html?bbox=' + bbox.join() + '&marker=' + data.lat + ',' + data.lon);

                        $(document.createElement('div'))
                            .prop('title', 'OpenStreetMap')
                            .css('background', 'url(' + OC.imagePath('core','loading.gif') + ') center center no-repeat')
                            .append(iframe)
                            .appendTo($('body'))
                            .ocdialog({
                                width: 900,
                                height: 680,
                                closeOnEscape: true,
                                modal: true,
                                close: function() {
                                    var _self = this;
                                    setTimeout(function() {
                                      $(_self).ocdialog('destroy').remove();
                                    }, 3000);
                                }
                            });
                    })
            }
        },

        updateLocation: function(data) {
            var html = '';

            if (data.error) {
                html = data.error;

            } else {
                var location = data.address;
                var address = [];
                this.add(location.building ? location.building : location.house_number, address);
                this.add(location.road ? location.road : location.footway, address);
                this.add(location.city, address);
                this.add(location.country, address);
                html = address.join(', ');
            }

            this.$el.find('.get-location').html(html);
        },

        add: function(val, array) {
            if (val) {
                array.push(val);
            }
        },
    });

    OCA.Metadata = OCA.Metadata || {};

    OCA.Metadata.MetadataTabView = MetadataTabView;
})();
