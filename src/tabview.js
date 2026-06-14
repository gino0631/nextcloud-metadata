import { getSidebar, FileType } from '@nextcloud/files'
import { generateUrl } from "@nextcloud/router"
import { t } from '@nextcloud/l10n'

import MetadataIconSvg from './info.svg' with { type: "text" }

class MetadataTabView extends HTMLElement {
    constructor() {
        super();
    }

    connectedCallback() {
        this.innerHTML = '<div style="text-align:center; word-wrap:break-word;" class="metadata-tab-view get-metadata"><p><br><img src="'
            + OC.imagePath('core', 'loading.gif')
            + '"><br><br></p><p>'
            + t('metadata', 'Reading metadata …')
            + '</p></div>';

        var url = generateUrl('/apps/metadata/get'),
            data = {source: this.node.dirname + '/' + this.node.basename},
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

    formatValue(value) {
        return Array.isArray(value) ? value.join('; ') : value;
    }

    updateDisplay(data) {
        var table;
        var showLocation = false;

        if (data.response === 'success') {
            table = $('<table>');

            var metadata = data.metadata;
            for (var m in metadata) {
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

        $(this).find('.get-metadata').empty().append(table);

        if (showLocation) {
            var _self = this;

            $(this).find('.get-location')
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
    }

    showMap(data) {
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
                modal: true
            });
    }

    updateLocation(data) {
        var text = '';

        if (data.error) {
            text = data.error;

        } else {
            var location = data.address;
            var address = [];
            this.add(location.building || location.attraction || location.artwork || location.monument || location.viewpoint || location.museum || location.cafe || location.shop || location.garden || location.aerodrome || location.address29 || location.house_number, address);
            this.add(location.road || location.pedestrian || location.path || location.steps || location.footway || location.cycleway || location.bridleway || location.construction, address);
            this.add(location.city || location.town || location.village || location.hamlet || location.isolated_dwelling, address);
            this.add(location.country, address);
            text = address.join(', ');
        }

        $(this).find('.get-location').text(text);
    }

    add(val, array) {
        if (val) {
            array.push(val);
        }
    }
}

getSidebar().registerTab({
    id: 'metadata',
    displayName: t('metadata', 'Metadata'),
    iconSvgInline: MetadataIconSvg,
    order: 70,
    tagName: 'metadata-files-sidebar-tab',

    enabled({ node }) {
        if (node.type === FileType.File) {
            return (['audio/flac', 'audio/mp4', 'audio/mpeg', 'audio/ogg', 'audio/wav',
                'image/gif', 'image/heic', 'image/jpeg', 'image/png', 'image/tiff', 'image/x-dcraw',
                'video/3gpp', 'video/dvd', 'video/MP2T', 'video/mp4', 'video/mpeg', 'video/quicktime',
                'video/webm', 'video/x-flv', 'video/x-matroska', 'video/x-msvideo',
                'application/pdf', 'application/zip'].indexOf(node.mime) > -1);
        }

        return false;
    },

    onInit() {
        customElements.define('metadata-files-sidebar-tab', MetadataTabView)
    }
});
