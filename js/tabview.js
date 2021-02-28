(function() {
    var MetadataTabView = OCA.Files.DetailTabView.extend({
        id: 'metadataTabView',
        className: 'tab metadataTabView',

        getLabel: function() {
            return t('metadata', 'Metadata');
        },

        getIcon: function() {
            return 'icon-details';
        },

        render: function() {
            var fileInfo = this.getFileInfo();
            
            if (fileInfo) {
                this.$el.html('<div style="text-align:center; word-wrap:break-word;" class="get-metadata"><p><img src="'
                    + OC.imagePath('core','loading.gif')
                    + '"><br><br></p><p>'
                    + t('metadata', 'Reading metadata …')
                    + '</p></div>');

                var url = OC.generateUrl('/apps/metadata/get'),
                    data = {source: fileInfo.getFullPath()},
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
            }
        },

        canDisplay: function(fileInfo) {
            if (!fileInfo || fileInfo.isDirectory()) {
                return false;
            }
            var mimetype = fileInfo.get('mimetype') || '';

            return (['audio/flac', 'audio/mp4', 'audio/mpeg', 'audio/ogg', 'audio/wav',
                'image/gif', 'image/heic', 'image/jpeg', 'image/png', 'image/tiff', 'image/x-dcraw',
                'video/3gpp', 'video/dvd', 'video/mp4', 'video/mpeg', 'video/quicktime',
                'video/webm', 'video/x-flv', 'video/x-matroska', 'video/x-msvideo',
                'application/zip'].indexOf(mimetype) > -1);
        },

        formatValue: function(value) {
            return Array.isArray(value) ? value.join('; ') : value;
        },

        updateDisplay: function(data) {
            var table;
            var showLocation = false;

            if (data.response === 'success') {
                table = $('<table>');

                var metadata = data.metadata;
                for (m in metadata) {
                    var row = $('<tr>')
                        .append($('<td>').addClass('key').text(m + ':'))
                        .append($('<td>').addClass('value').text(this.formatValue(metadata[m])));
                    table.append(row);
                }

                showLocation = (data.loc !== null) || ((data.lat !== null) && (data.lon !== null));
                if (showLocation) {
                    var location;

                    if (data.loc !== null) {
                        var address = [];
                        this.add(data.loc.city, address);
                        this.add(data.loc.state, address);
                        this.add(data.loc.country, address);
                        location = address.join(', ');

                    } else {
                        location = t('metadata', 'Resolving, click here to view on map …');
                    }

                    if ((data.lat !== null) && (data.lon !== null)) {
                        var url = 'https://nominatim.openstreetmap.org/reverse',
                            params = {lat: data.lat, lon: data.lon, format: 'json', zoom: 18},
                            _self = this;
                        $.ajax({
                            type: 'GET',
                            url: url,
                            dataType: 'json',
                            data: params,
                            async: true,
                            success: function(data) {
                                _self.updateLocation(data);
                            },
                            error: function() {
                                if (data.loc === null) {
                                    _self.updateLocation({error: t('metadata', 'Nominatim service unavailable, click here to view on map')});
                                }
                            }
                        });
                    }

                    var row = $('<tr>')
                        .append($('<td>').addClass('key').text(t('metadata', 'Location') + ':'))
                        .append($('<td>').addClass('value').append($('<a>').attr('href', '#').addClass('get-location').text(location)));
                    table.append(row);
                }

            } else {
                table = $('<p>').text(data.msg);
            }

            this.$el.find('.get-metadata').empty().append(table);

            if (showLocation) {
                var _self = this;

                this.$el.find('.get-location')
                    .click(function() {
                        if ((data.lat === null) || (data.lon === null)) {
                            var url = 'https://nominatim.openstreetmap.org/search',
                                params = {city: data.loc.city, state: data.loc.state, country: data.loc.country, format: 'json', limit: 1};
                            $.ajax({
                                type: 'GET',
                                url: url,
                                dataType: 'json',
                                data: params,
                                async: true,
                                success: function(data) {
                                    if (data.length > 0) {
                                        _self.showMap(data[0]);

                                    } else {
                                        console.log(t('metadata', 'Location could not be determined'));
                                    }
                                },
                                error: function() {
                                    console.log(t('metadata', 'Nominatim service unavailable'));
                                }
                            });

                        } else {
                            _self.showMap(data);
                        }
                    })
            }
        },

        showMap: function(data) {
            var bbox = [data.lon - 0.0051, data.lat - 0.0051, data.lon - -0.0051, data.lat - -0.0051];

            var iframe = document.createElement('iframe');
            iframe.setAttribute('width', '100%');
            iframe.setAttribute('height', '100%');
            iframe.setAttribute('src', 'https://www.openstreetmap.org/export/embed.html?bbox=' + bbox.join() + '&marker=' + data.lat + ',' + data.lon);

            $(document.createElement('div'))
                .prop('title', 'OpenStreetMap')
                .css('background', 'url(' + OC.imagePath('core','loading.gif') + ') center center no-repeat')
                .css('max-width', 'none')
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
        },

        updateLocation: function(data) {
            var text = '';

            if (data.error) {
                text = data.error;

            } else {
                var location = data.address;
                var address = [];
                this.add(location.building || location.attraction || location.artwork || location.monument || location.viewpoint || location.museum || location.cafe || location.garden || location.aerodrome || location.address29 || location.house_number, address);
                this.add(location.road || location.pedestrian || location.path || location.steps || location.footway || location.cycleway || location.bridleway || location.construction, address);
                this.add(location.city || location.town || location.village || location.hamlet || location.isolated_dwelling, address);
                this.add(location.country, address);
                text = address.join(', ');
            }

            this.$el.find('.get-location').text(text);
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
