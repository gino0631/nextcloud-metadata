import axios from 'axios'
// Adds the Nextcloud request token to every request, so it is only used for the app's own routes
import nextcloudAxios from '@nextcloud/axios'
import { getSidebar, FileType } from '@nextcloud/files'
import { generateUrl, imagePath } from "@nextcloud/router"
import { t } from '@nextcloud/l10n'

import MetadataIconSvg from './info.svg' with { type: "text" }

const CloseIconSvg = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z"/></svg>';

class MetadataTabView extends HTMLElement {
    constructor() {
        super();
    }

    connectedCallback() {
        this.innerHTML = '<div style="text-align:center; word-wrap:break-word;" class="metadata-tab-view get-metadata"><p><br><img src="'
            + imagePath('core', 'loading.gif')
            + '"><br><br></p><p>'
            + t('metadata', 'Reading metadata …')
            + '</p></div>';

        var url = generateUrl('/apps/metadata/get'),
            params = {source: this.node.dirname + '/' + this.node.basename},
            _self = this;
        nextcloudAxios.get(url, {params: params}).then(function(response) {
            _self.updateDisplay(response.data);
        });
    }

    formatValue(value) {
        return Array.isArray(value) ? value.join('; ') : value;
    }

    updateDisplay(data) {
        var table;
        var showLocation = false;

        if (data.response === 'success') {
            table = document.createElement('table');

            var metadata = data.metadata;
            for (var m in metadata) {
                table.append(this.createRow(m + ':', this.createElement('td', 'value', this.formatValue(metadata[m]))));
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
                    axios.get(url, {params: params}).then(function(response) {
                        _self.updateLocation(response.data);
                    }).catch(function() {
                        if (data.loc === null) {
                            _self.updateLocation({error: t('metadata', 'Nominatim service unavailable, click here to view on map')});
                        }
                    });
                }

                var link = this.createElement('a', 'get-location', location);
                link.setAttribute('href', '#');
                var value = this.createElement('td', 'value');
                value.append(link);
                table.append(this.createRow(t('metadata', 'Location') + ':', value));
            }

        } else {
            table = this.createElement('p', null, data.msg);
        }

        this.querySelector('.get-metadata').replaceChildren(table);

        if (showLocation) {
            var _self = this;

            this.querySelector('.get-location')
                .addEventListener('click', function(event) {
                    event.preventDefault();

                    if ((data.lat === null) || (data.lon === null)) {
                        var url = 'https://nominatim.openstreetmap.org/search',
                            params = {city: data.loc.city, state: data.loc.state, country: data.loc.country, format: 'json', limit: 1};
                        axios.get(url, {params: params}).then(function(response) {
                            if (response.data.length > 0) {
                                _self.showMap(response.data[0]);

                            } else {
                                console.log(t('metadata', 'Location could not be determined'));
                            }
                        }).catch(function() {
                            console.log(t('metadata', 'Nominatim service unavailable'));
                        });

                    } else {
                        _self.showMap(data);
                    }
                });
        }
    }

    showMap(data) {
        var bbox = [data.lon - 0.0051, data.lat - 0.0051, data.lon - -0.0051, data.lat - -0.0051];

        var iframe = document.createElement('iframe');
        iframe.setAttribute('width', '100%');
        iframe.setAttribute('height', '100%');
        iframe.setAttribute('src', 'https://www.openstreetmap.org/export/embed.html?bbox=' + bbox.join() + '&marker=' + data.lat + ',' + data.lon);

        var close = this.createElement('button', 'metadata-map-close');
        close.setAttribute('aria-label', t('metadata', 'Close'));
        close.innerHTML = CloseIconSvg;

        // A form with method "dialog" closes its dialog when submitted
        var header = this.createElement('form', 'metadata-map-header');
        header.setAttribute('method', 'dialog');
        header.append(this.createElement('h2', null, 'OpenStreetMap'), close);

        var map = this.createElement('div', 'metadata-map-content');
        map.style.backgroundImage = 'url(' + imagePath('core', 'loading.gif') + ')';
        map.append(iframe);

        var dialog = this.createElement('dialog', 'metadata-map');
        dialog.setAttribute('aria-label', 'OpenStreetMap');
        dialog.append(header, map);
        dialog.addEventListener('keydown', function(event) {
            // The files app prevents the default of Escape (which would close the dialog) and closes the sidebar
            if (event.key === 'Escape') {
                event.stopPropagation();
                dialog.close();
            }
        });
        dialog.addEventListener('click', function(event) {
            // A click on the backdrop targets the dialog itself
            if (event.target === dialog) {
                dialog.close();
            }
        });
        dialog.addEventListener('close', function() {
            dialog.remove();
        });

        document.body.append(dialog);
        dialog.showModal();
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

        // The tab may have been rendered again while waiting for the response
        var link = this.querySelector('.get-location');
        if (link) {
            link.textContent = text;
        }
    }

    createElement(tagName, className, text) {
        var element = document.createElement(tagName);
        if (className) {
            element.className = className;
        }
        if (text !== undefined) {
            element.textContent = text;
        }

        return element;
    }

    createRow(key, value) {
        var row = document.createElement('tr');
        row.append(this.createElement('td', 'key', key), value);

        return row;
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
