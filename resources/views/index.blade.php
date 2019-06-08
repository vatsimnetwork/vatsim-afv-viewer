@extends('_layouts.master')

@section('pageTitle')
    <span id="online-count"><i class="la la-refresh la-spin"></i></span>
@endsection


@section('page_css')
    <link rel="stylesheet" type="text/css" href="{{ app_asset_path('vendors/leaflet/leaflet.css') }}">
@endsection


@section('page_js')
    <script type="text/javascript" src="{{ app_asset_path('vendors/leaflet/leaflet.js') }}"></script>
    <script type="text/javascript" src="{{ app_asset_path('vendors/leaflet/leaflet-geodesic.js') }}"></script>
    <script type="text/javascript" src="{{ app_asset_path('vendors/leaflet/leaflet-rotation.js') }}"></script>
@endsection


@section('content')

    <div class="row">
        <div class="col-md-3">
            <section class="card">
                <div class="card-content">
                    <div class="card-body">
                        <button class="btn btn-sm" id="togglePilotRings">Toggle Pilot Rings</button>
                        <button class="btn btn-sm" id="toggleAtcRings">Toggle ATC Rings</button>
                        <br><br>
                        <div id="atis-list" style="height: calc(100vh - 300px); overflow: auto;">

                        </div>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-md-9">
            <section class="card">
                <div class="card-content">
                    <div class="card-body" id="flightMap" style="height: calc(100vh - 300px);"></div>
                </div>
            </section>
        </div>
    </div>

    <script>
        pilotRings = true;
        atcRings = true;

        var map = L.map('flightMap').setView([44.341393, -3.915340], 2);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            subdomains: 'abcd',
            maxZoom: 19
        }).addTo(map);

        mapMarkers = [];
        onlineTransceivers = [];
        clients = [];
        $.ajax({
            type: 'get',
            url: '{{ route('atis-map-data') }}',
            success: function(data) {
                data.forEach(function (client) {
                    client['transceivers'].forEach(function (transceiver) {
                        var callsign = client.callsign;
                        var frequency = transceiver.frequency;
                        var lat = transceiver.latDeg;
                        var lon = transceiver.lonDeg;
                        var msl = transceiver.altMslM;

                        var content = '<b>' + callsign +'</b><br>';
                        content += frequency;

                        if(callsign.includes('ATIS')) {
                            var marker = L.marker([lat, lon], {
                                icon: L.icon({
                                    iconUrl: '{{ asset_path('img/map/pin.png') }}',
                                    iconSize: [10, 17],
                                    iconAnchor: [5, 17]
                                }),
                            });
                        } else if (callsign.includes('_DEL') || callsign.includes('_GND') || callsign.includes('_TWR') || callsign.includes('_APP') || callsign.includes('_DEP') || callsign.includes('_CTR') || callsign.includes('_FSS')) { // controller
                            var marker = L.marker([lat, lon], {
                                icon: L.icon({
                                    iconUrl: '{{ asset_path('img/map/green-pin.png') }}',
                                    iconSize: [10, 16],
                                    iconAnchor: [5, 16]
                                }),
                            });
                        } else { // plane
                            var marker = L.marker([lat, lon], {
                                icon: L.icon({
                                    iconUrl: '{{ asset_path('img/map/red-pin.png') }}',
                                    iconSize: [10, 16],
                                    iconAnchor: [5, 16]
                                }),
                            });
                        }

                        if (callsign.includes('_DEL') || callsign.includes('_GND') || callsign.includes('_TWR') || callsign.includes('_APP') || callsign.includes('_DEP') || callsign.includes('_CTR') || callsign.includes('_FSS')) {
                            var RadiusMeters = (1.25 * Math.sqrt(msl * 3.28084)) * 1852;
                            L.circle([lat, lon], {radius: RadiusMeters, fillOpacity: 0, color: '#418041'}).addTo(map);
                        } else if (!callsign.includes('_')) {
                            var RadiusMeters = (1.25 * Math.sqrt(msl * 3.28084)) * 1852;
                            L.circle([lat, lon], {radius: RadiusMeters, fillOpacity: 0, color: '#ce6262'}).addTo(map);
                        }

                        marker.addTo(map).bindPopup(content);
                        mapMarkers.push(marker);
                        onlineTransceivers.push({callsign: callsign, freq: frequency});
                    });
                });

                if(mapMarkers.length > 0) {
                    map.fitBounds(L.featureGroup(mapMarkers).getBounds());
                    onlineTransceivers.sort(function(a, b) {
                        return ((a.callsign < b.callsign) ? -1 : ((a.callsign > b.callsign) ? 1 : 0));
                    });

                    // [{client: '', frequencies:['123.000','122.800']]]
                    onlineTransceivers.forEach(function(onlineTransceiver) {
                        var clientIndex = clients.findIndex(x => x.callsign === onlineTransceiver.callsign);
                        if(clientIndex > -1) {
                            // add the frequency to array
                            var client = clients.find(x => x.callsign === onlineTransceiver.callsign);
                            clients.splice(clientIndex, 1);
                            client['frequencies'].push(onlineTransceiver.freq);
                            clients.push(client);
                        } else {
                            // create a new element
                            clients.push({callsign: onlineTransceiver.callsign, frequencies: [onlineTransceiver.freq]});
                        }
                    });

                    clients.forEach(function (client) {
                        $('#atis-list').append('<h5 style="margin-bottom: 0;">' + client.callsign + ' - ' + client['frequencies'].join(',') + '</h5>');
                    });
                    $('#online-count').html(clients.length + ' Voice Clients Connected');
                } else {
                    $('#atisList').append('<h5 style="margin-bottom: 0;">No Voice Clients</h5>');
                    $('#online-count').html('0 Voice Clients Connected');
                }
            }
        });

        $('#togglePilotRings').click(function () {
            if(pilotRings == true) {
                $('path[stroke="#ce6262"]').hide();
                pilotRings = false;
            } else {
                $('path[stroke="#ce6262"]').show();
                pilotRings = true;
            }
        });

        $('#toggleAtcRings').click(function () {
            if(atcRings == true) {
                $('path[stroke="#418041"]').hide();
                atcRings = false;
            } else {
                $('path[stroke="#418041"]').show();
                atcRings = true;
            }
        });

        setInterval(function() {
            $.ajax({
                type: 'get',
                url: '{{ route('atis-map-data') }}',
                success: function (data) {
                    for (var i = 0; i < mapMarkers.length; i++) {
                        map.removeLayer(mapMarkers[i]);
                    }
                    $('path.leaflet-interactive').remove();
                    mapMarkers = [];
                    onlineTransceivers = [];
                    $('#atisList').html('');
                    data.forEach(function (client) {
                        client['transceivers'].forEach(function (transceiver) {
                            var callsign = client.callsign;
                            var frequency = transceiver.frequency;
                            var lat = transceiver.latDeg;
                            var lon = transceiver.lonDeg;
                            var msl = transceiver.altMslM;

                            var content = '<b>' + callsign +'</b><br>';
                            content += frequency;

                            if(callsign.includes('ATIS')) {
                                var marker = L.marker([lat, lon], {
                                    icon: L.icon({
                                        iconUrl: '{{ asset_path('img/map/pin.png') }}',
                                        iconSize: [10, 17],
                                        iconAnchor: [5, 17]
                                    }),
                                });
                            } else if (callsign.includes('_DEL') || callsign.includes('_GND') || callsign.includes('_TWR') || callsign.includes('_APP') || callsign.includes('_DEP') || callsign.includes('_CTR') || callsign.includes('_FSS')) { // controller
                                var marker = L.marker([lat, lon], {
                                    icon: L.icon({
                                        iconUrl: '{{ asset_path('img/map/green-pin.png') }}',
                                        iconSize: [10, 16],
                                        iconAnchor: [5, 16]
                                    }),
                                });
                            } else { // plane
                                var marker = L.marker([lat, lon], {
                                    icon: L.icon({
                                        iconUrl: '{{ asset_path('img/map/red-pin.png') }}',
                                        iconSize: [10, 16],
                                        iconAnchor: [5, 16]
                                    }),
                                });
                            }

                            if (callsign.includes('_DEL') || callsign.includes('_GND') || callsign.includes('_TWR') || callsign.includes('_APP') || callsign.includes('_DEP') || callsign.includes('_CTR') || callsign.includes('_FSS')) {
                                var RadiusMeters = (1.25 * Math.sqrt(msl * 3.28084)) * 1852;
                                L.circle([lat, lon], {radius: RadiusMeters, fillOpacity: 0, color: '#418041'}).addTo(map);
                            } else if (!callsign.includes('_')) {
                                var RadiusMeters = (1.25 * Math.sqrt(msl * 3.28084)) * 1852;
                                L.circle([lat, lon], {radius: RadiusMeters, fillOpacity: 0, color: '#ce6262'}).addTo(map);
                            }

                            marker.addTo(map).bindPopup(content);
                            mapMarkers.push(marker);
                            onlineTransceivers.push({callsign: callsign, freq: frequency});
                        });
                    });

                    if(pilotRings == 0) {
                        $('path[stroke="#ce6262"]').hide();
                    }

                    if(atcRings == 0) {
                        $('path[stroke="#418041"]').hide();
                    }

                    if(mapMarkers.length > 0) {
                        map.fitBounds(L.featureGroup(mapMarkers).getBounds());
                        onlineTransceivers.sort(function(a, b) {
                            return ((a.callsign < b.callsign) ? -1 : ((a.callsign > b.callsign) ? 1 : 0));
                        });

                        // [{client: '', frequencies:['123.000','122.800']]]
                        onlineTransceivers.forEach(function(onlineTransceiver) {
                            var clientIndex = clients.findIndex(x => x.callsign === onlineTransceiver.callsign);
                            if(clientIndex > -1) {
                                // add the frequency to array
                                var client = clients.find(x => x.callsign === onlineTransceiver.callsign);
                                clients.splice(clientIndex, 1);
                                client['frequencies'].push(onlineTransceiver.freq);
                                clients.push(client);
                            } else {
                                // create a new element
                                clients.push({callsign: onlineTransceiver.callsign, frequencies: [onlineTransceiver.freq]});
                            }
                        });

                        clients.forEach(function (client) {
                            $('#atis-list').append('<h5 style="margin-bottom: 0;">' + client.callsign + ' - ' + client['frequencies'].join(',') + '</h5>');
                        });
                        $('#online-count').html(clients.length + ' Voice Clients Connected');
                    } else {
                        $('#atisList').append('<h5 style="margin-bottom: 0;">No Voice Clients</h5>');
                        $('#online-count').html('0 Voice Clients Connected');
                    }
                }
            });
        }, 30000);

    </script>

@endsection