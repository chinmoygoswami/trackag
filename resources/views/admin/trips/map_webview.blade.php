<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trip Route Map</title>
    
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Font Awesome CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            width: 100%;
            overflow: hidden;
            font-family: 'Source Sans 3', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f5f6fa;
        }

        #map {
            width: 100%;
            height: 100%;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 1;
        }

        /* Floating Info and Legends Overlay */
        .overlay-container {
            position: absolute;
            top: 12px;
            left: 12px;
            right: 12px;
            z-index: 10;
            pointer-events: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .floating-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 10px 14px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(229, 231, 235, 0.8);
            pointer-events: auto;
        }

        .trip-info-header {
            font-size: 13px;
            color: #374151;
        }

        .legends-row-container {
            position: absolute;
            bottom: 24px;
            left: 12px;
            right: auto;
            max-width: calc(100% - 60px);
            z-index: 10;
            pointer-events: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .route-legend {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 10px 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(229, 231, 235, 0.8);
            pointer-events: auto;
        }

        .legend-title {
            font-weight: 600;
            font-size: 12px;
            margin-bottom: 6px;
            color: #111827;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .legend-row {
            display: flex;
            align-items: center;
            gap: 12px;
            overflow-x: auto;
            white-space: nowrap;
            scrollbar-width: none; /* Hide scrollbar for Firefox */
        }
        .legend-row::-webkit-scrollbar {
            display: none; /* Hide scrollbar for Chrome, Safari and Opera */
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: #4b5563;
        }

        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }

        .legend-dot.green { background-color: #28a745; }
        .legend-dot.blue { background-color: rgb(0, 45, 255); }
        .legend-dot.red { background-color: #dc3545; }

        .legend-mini-marker {
            width: 18px;
            height: 18px;
            background: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8px;
            border: 1.5px solid #e5e7eb;
        }

        .lm-start { border-color: #10b981; color: #10b981; }
        .lm-end { border-color: #ef4444; color: #ef4444; }
        .lm-party { border-color: #f59e0b; color: #f59e0b; }
        .lm-farmer { border-color: #10b981; color: #10b981; }
        .lm-farm-visit { border-color: #3b82f6; color: #3b82f6; }
        .lm-customer { border-color: #ef4444; color: #ef4444; }

        /* ---------- CUSTOM HTML MARKERS ---------- */
        .modern-marker {
            width: 36px;
            height: 36px;
            background: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2);
            border: 2px solid #e5e7eb;
            font-size: 14px;
            color: #4b5563;
            transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s ease;
            cursor: pointer;
        }

        .modern-marker.start { border-color: #10b981; color: #10b981; }
        .modern-marker.end { border-color: #ef4444; color: #ef4444; }
        .modern-marker.party-visit { border-color: #f59e0b; color: #f59e0b; }
        .modern-marker.farmer { border-color: #10b981; color: #10b981; }
        .modern-marker.farm-visit { border-color: #3b82f6; color: #3b82f6; }
        .modern-marker.customer { border-color: #ef4444; color: #ef4444; }

        /* Small Dots for Path */
        .modern-marker.middle-green, 
        .modern-marker.middle-blue, 
        .modern-marker.middle-red {
            width: 14px;
            height: 14px;
            background: transparent;
            border: none;
            box-shadow: none;
        }

        .modern-marker .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            border: 1.5px solid #fff;
            box-shadow: 0 1.5px 3px rgba(0,0,0,0.3);
        }

        .green-dot { background: #10b981; }
        .blue-dot  { background: #3b82f6; }
        .red-dot   { background: #ef4444; }

        /* Pulse Animation */
        .pulse-marker {
            width: 20px;
            height: 20px;
            background: transparent;
            border: none;
            box-shadow: none;
            position: relative;
        }
        .pulse-core {
            width: 12px;
            height: 12px;
            background: #3b82f6;
            border-radius: 50%;
            border: 1.5px solid #fff;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            box-shadow: 0 1.5px 3px rgba(0,0,0,0.3);
            z-index: 2;
        }
        .pulse-ring {
            width: 32px;
            height: 32px;
            background: rgba(59, 130, 246, 0.4);
            border-radius: 50%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation: pulse-animation 2s infinite cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1;
        }
        @keyframes pulse-animation {
            0% { transform: translate(-50%, -50%) scale(0.5); opacity: 1; }
            100% { transform: translate(-50%, -50%) scale(2); opacity: 0; }
        }

        /* ---------- INFOWINDOW OVERRIDES ---------- */
        .gm-style-iw-c {
            padding: 0 !important;
            border-radius: 12px !important;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15) !important;
        }
        .gm-style-iw-d {
            overflow: hidden !important;
        }
        .modern-iw-content {
            font-family: inherit;
            min-width: 180px;
            color: #1f2937;
        }
        .modern-iw-content .iw-header {
            background: #f8fafc;
            padding: 10px 14px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }
        .modern-iw-content .iw-body {
            padding: 10px 14px;
            font-size: 12px;
        }
        .modern-iw-content .iw-body div {
            margin-bottom: 3px;
        }
        .modern-iw-content .iw-body span {
            color: #64748b;
            font-weight: 500;
        }
    </style>
</head>
<body>

    <!-- Floating header details -->
    <div class="overlay-container">
        <div class="floating-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fw-bold m-0 text-dark" style="font-size: 14px;">
                        <i class="fas fa-map-marked-alt text-primary me-1"></i> Trip Map
                    </h6>
                    <div class="trip-info-header mt-1">
                        <strong>Agent:</strong> {{ $trip->user->name ?? 'N/A' }} | 
                        <strong>Date:</strong> {{ \Carbon\Carbon::parse($trip->trip_date)->format('d-m-Y') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Map container -->
    <div id="map"></div>

    <!-- Floating Legends at Bottom -->
    <div class="legends-row-container">
        <!-- Route Time Legend -->
        <div class="route-legend">
            <div class="legend-title">Route Time Legend</div>
            <div class="legend-row">
                <div class="legend-item">
                    <span class="legend-dot green"></span>
                    <span>6 AM – 12 PM</span>
                </div>
                <div class="legend-item">
                    <span class="legend-dot blue"></span>
                    <span>12 PM – 6 PM</span>
                </div>
                <div class="legend-item">
                    <span class="legend-dot red"></span>
                    <span>6 PM – 12 AM</span>
                </div>
            </div>
        </div>

        <!-- Map Icon Legend -->
        <div class="route-legend">
            <div class="legend-title">Map Icon Legend</div>
            <div class="legend-row">
                <div class="legend-item">
                    <div class="legend-mini-marker lm-start"><i class="fas fa-play"></i></div>
                    <span>Start</span>
                </div>
                <div class="legend-item">
                    <div class="legend-mini-marker lm-end"><i class="fas fa-flag-checkered"></i></div>
                    <span>End</span>
                </div>
                <div class="legend-item">
                    <div class="legend-mini-marker lm-party"><i class="fas fa-building"></i></div>
                    <span>Party</span>
                </div>
                <div class="legend-item">
                    <div class="legend-mini-marker lm-farmer"><i class="fas fa-user-tie"></i></div>
                    <span>Farmer</span>
                </div>
                <div class="legend-item">
                    <div class="legend-mini-marker lm-farm-visit"><i class="fas fa-leaf"></i></div>
                    <span>Farm Visit</span>
                </div>
                <div class="legend-item">
                    <div class="legend-mini-marker lm-customer"><i class="fas fa-store"></i></div>
                    <span>Customer</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Pass Data to JS -->
    <script>
        window.tripLogs = @json($tripLogs);
        window.tripEnded = {{ $trip->end_time ? 'true' : 'false' }};
        window.partyVisits = @json($partyVisits);
        window.farmers = @json($farmers);
        window.farmVisits = @json($farmVisits);
        window.customers = @json($customers);
    </script>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}&libraries=places,marker&callback=initMap" async defer></script>

    <!-- Map Script -->
    <script>
        async function initMap() {
            const tripLogs = window.tripLogs || [];
            const partyVisits = window.partyVisits || [];
            const farmers = window.farmers || [];
            const farmVisits = window.farmVisits || [];
            const customers = window.customers || []; 
            const tripEnded = window.tripEnded;

            if (!tripLogs.length) {
                console.warn("No trip logs found");
                return;
            }

            const { Map } = await google.maps.importLibrary("maps");
            const { AdvancedMarkerElement } = await google.maps.importLibrary("marker");

            const pathCoordinates = tripLogs.map(l => ({
                lat: parseFloat(l.latitude),
                lng: parseFloat(l.longitude),
                recorded_at: l.recorded_at
            }));

            const map = new Map(document.getElementById("map"), {
                zoom: 14,
                center: pathCoordinates[0],
                mapId: "DEMO_MAP_ID",
                disableDefaultUI: true, // cleaner UI for webview
                zoomControl: true,
                zoomControlOptions: {
                    position: google.maps.ControlPosition.RIGHT_CENTER
                }
            });

            // ---------- POLYLINE PATH ----------
            if (pathCoordinates.length > 1) {
                new google.maps.Polyline({
                    path: pathCoordinates,
                    geodesic: true,
                    strokeColor: "#3b82f6",
                    strokeOpacity: 0.3,
                    strokeWeight: 8,
                    map
                });
                new google.maps.Polyline({
                    path: pathCoordinates,
                    geodesic: true,
                    strokeColor: "#2563eb",
                    strokeOpacity: 1.0,
                    strokeWeight: 3,
                    map
                });
            }

            // Helper for custom markers
            function createCustomMarker(type, iconClass) {
                const el = document.createElement("div");
                el.className = `modern-marker ${type}`;
                
                let innerHtml = '';
                if (type === 'start') innerHtml = '<i class="fas fa-play"></i>';
                else if (type === 'end') innerHtml = '<i class="fas fa-flag-checkered"></i>';
                else if (type === 'middle-green') innerHtml = '<div class="dot green-dot"></div>';
                else if (type === 'middle-blue') innerHtml = '<div class="dot blue-dot"></div>';
                else if (type === 'middle-red') innerHtml = '<div class="dot red-dot"></div>';
                else if (iconClass) innerHtml = `<i class="${iconClass}"></i>`;

                if (type === 'pulse') {
                    el.className += ' pulse-marker';
                    innerHtml = '<div class="pulse-ring"></div><div class="pulse-core"></div>';
                }

                el.innerHTML = innerHtml;
                return el;
            }

            // ---------- START ----------
            new AdvancedMarkerElement({
                position: pathCoordinates[0],
                map,
                content: createCustomMarker('start'),
                title: "Start: " + (pathCoordinates[0].recorded_at ?? '')
            });

            // ---------- MIDDLE POINTS ----------
            for (let i = 1; i < pathCoordinates.length - 1; i++) {
                let type = 'middle-blue';

                if (pathCoordinates[i].recorded_at) {
                    const parts = pathCoordinates[i].recorded_at.split(' ');
                    // Simple parse if matches format d-m-Y H:i:s a
                    let hour = 12;
                    if (parts.length >= 2) {
                        const timeParts = parts[1].split(':');
                        if (timeParts.length > 0) {
                            hour = parseInt(timeParts[0]);
                            const ampm = parts[2] ? parts[2].toLowerCase() : 'am';
                            if (ampm === 'pm' && hour < 12) hour += 12;
                            if (ampm === 'am' && hour === 12) hour = 0;
                        }
                    }

                    if (hour >= 6 && hour < 12) {
                        type = 'middle-green';
                    } else if (hour >= 12 && hour < 18) {
                        type = 'middle-blue';
                    } else {
                        type = 'middle-red';
                    }
                }

                new AdvancedMarkerElement({
                    position: pathCoordinates[i],
                    map,
                    content: createCustomMarker(type),
                    title: pathCoordinates[i].recorded_at
                });
            }

            // ---------- END / CURRENT LOCATION ----------
            if (pathCoordinates.length > 1) {
                const last = pathCoordinates[pathCoordinates.length - 1];
                new AdvancedMarkerElement({
                    position: last,
                    map,
                    content: tripEnded ? createCustomMarker('end') : createCustomMarker('pulse'),
                    title: tripEnded ? ("End: " + (last.recorded_at ?? '')) : ("Current Location: " + (last.recorded_at ?? '')),
                    zIndex: 999
                });
            }

            // ---------- INFOWINDOW ----------
            const infoWindow = new google.maps.InfoWindow();

            function setupHoverInfoWindow(marker, content) {
                marker.addListener("click", () => {
                     const wrapper = `<div class="modern-iw-content">${content}</div>`;
                     infoWindow.setContent(wrapper);
                     infoWindow.open(map, marker);
                });
            }

            // ---------- PARTY VISITS ----------
            partyVisits.forEach(party => {
                if (!party.latitude || !party.longitude) return;
                const agroName = party.customer?.agro_name ?? 'Customer';
                const marker = new AdvancedMarkerElement({
                    position: { lat: parseFloat(party.latitude), lng: parseFloat(party.longitude) },
                    map,
                    content: createCustomMarker('party-visit', 'fas fa-building'),
                    title: agroName
                });
                setupHoverInfoWindow(marker, `
                    <div class="iw-header">
                        <i class="fas fa-building text-warning"></i>
                        <strong>Party Visit</strong>
                    </div>
                    <div class="iw-body">
                        <div><span>Name:</span> ${agroName}</div>
                        <div><span>Check-in:</span> ${party.check_in_time ?? 'N/A'}</div>
                    </div>
                `);
            });

            // ---------- FARMERS ----------
            farmers.forEach(farmer => {
                if (!farmer.latitude || !farmer.longitude) return;
                const marker = new AdvancedMarkerElement({
                    position: { lat: parseFloat(farmer.latitude), lng: parseFloat(farmer.longitude) },
                    map,
                    content: createCustomMarker('farmer', 'fas fa-user-tie'),
                    title: farmer.farmer_name ?? 'Farmer'
                });
                setupHoverInfoWindow(marker, `
                    <div class="iw-header">
                        <i class="fas fa-user-tie text-success"></i>
                        <strong>Farmer Location</strong>
                    </div>
                    <div class="iw-body">
                        <div><span>Name:</span> ${farmer.farmer_name ?? 'N/A'}</div>
                        <div><span>Date:</span> ${farmer.created_at ?? 'N/A'}</div>
                    </div>
                `);
            });

            // ---------- FARM VISITS ----------
            farmVisits.forEach(visit => {
                if (!visit.latitude || !visit.longitude) return;
                const marker = new AdvancedMarkerElement({
                    position: { lat: parseFloat(visit.latitude), lng: parseFloat(visit.longitude) },
                    map,
                    content: createCustomMarker('farm-visit', 'fas fa-leaf'),
                    title: visit.farmer_name ?? 'Farm Visit'
                });
                setupHoverInfoWindow(marker, `
                    <div class="iw-header">
                        <i class="fas fa-leaf text-success"></i>
                        <strong>Farm Visit</strong>
                    </div>
                    <div class="iw-body">
                        <div><span>Farmer:</span> ${visit.farmer_name ?? 'N/A'}</div>
                        <div><span>Date:</span> ${visit.created_at ?? 'N/A'}</div>
                    </div>
                `);
            });

            // ---------- CUSTOMERS ----------
            customers.forEach(customer => {
                if (!customer.latitude || !customer.longitude) return;
                const marker = new AdvancedMarkerElement({
                    position: { lat: parseFloat(customer.latitude), lng: parseFloat(customer.longitude) },
                    map,
                    content: createCustomMarker('customer', 'fas fa-store'),
                    title: customer.agro_name ?? 'Customer'
                });
                setupHoverInfoWindow(marker, `
                    <div class="iw-header">
                        <i class="fas fa-store text-danger"></i>
                        <strong>Customer Visit</strong>
                    </div>
                    <div class="iw-body">
                        <div><span>Name:</span> ${customer.agro_name ?? 'N/A'}</div>
                        <div><span>Date:</span> ${customer.created_at ?? 'N/A'}</div>
                    </div>
                `);
            });
        }
    </script>
</body>
</html>
