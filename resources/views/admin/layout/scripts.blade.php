<!-- jQuery (one version only, 3.7.0 latest) -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<!-- Bootstrap 5 + Popper -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous">
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>

<!-- Datatables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/jquery.dataTables.min.css" />
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css" />
<script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>

<!-- OverlayScrollbars -->
<script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/browser/overlayscrollbars.browser.es6.min.js"
    crossorigin="anonymous"></script>

<!-- AdminLTE JS -->
<script src="{{ asset('admin/js/adminlte.js') }}"></script>

<!-- SortableJS -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js" crossorigin="anonymous"></script>

<!-- ApexCharts -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.min.js" crossorigin="anonymous"></script>

<!-- jsvectormap -->
<script src="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/js/jsvectormap.min.js" crossorigin="anonymous">
</script>
<script src="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/maps/world.js" crossorigin="anonymous"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@9"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Google Maps API -->
{{-- <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyB_-uOyQimLqBkDW_Vr8d88GX6Qk0lyksI&libraries=places"> --}}
{{-- <script src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}&libraries=places" ></script> --}}
<script
    src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}&libraries=places,marker&callback=initMap"
    async defer>
</script>

</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<!-- Custom JS -->
<script src="{{ url('admin/js/custom.js') }}"></script>

<!-- Initialize DataTables -->
<script>
    $(document).ready(function() {
        $("#users-table, #permissions-table,#states-table,#tehsils-table,#districts-table")
            .DataTable();
    });
</script>

<!-- OverlayScrollbars Config -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const sidebarWrapper = document.querySelector('.sidebar-wrapper');
        if (sidebarWrapper && typeof OverlayScrollbarsGlobal?.OverlayScrollbars !== 'undefined') {
            OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
                scrollbars: {
                    theme: 'os-theme-light',
                    autoHide: 'leave',
                    clickScroll: true
                }
            });
        }
    });
</script>

<!-- Sortable Cards -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.connectedSortable').forEach(el => {
            new Sortable(el, {
                group: 'shared',
                handle: '.card-header'
            });
        });
        document.querySelectorAll('.connectedSortable .card-header').forEach(el => el.style.cursor = 'move');
    });
</script>

<script>

async function initMap() {

    const tripLogs     = window.tripLogs || [];
    const partyVisits  = window.partyVisits || [];
    const farmers      = window.farmers || [];
    const farmVisits   = window.farmVisits || [];
    const customers    = window.customers || []; 
    const tripEnded    = window.tripEnded;

    if (!tripLogs.length) {
        console.warn("No trip logs found");
        return;
    }

    // Dynamic library import for Advanced Markers
    const { Map } = await google.maps.importLibrary("maps");
    const { AdvancedMarkerElement } = await google.maps.importLibrary("marker");

    const pathCoordinates = tripLogs.map(l => ({
        lat: parseFloat(l.latitude),
        lng: parseFloat(l.longitude),
        recorded_at: l.recorded_at
    }));

    const map = new Map(document.getElementById("map"), {
        zoom: 13,
        center: pathCoordinates[0],
        mapId: "DEMO_MAP_ID", // Required for AdvancedMarkerElement
    });

    // ---------- GLOW POLYLINE ----------
    if (pathCoordinates.length > 1) {
        // Outer glow
        new google.maps.Polyline({
            path: pathCoordinates,
            geodesic: true,
            strokeColor: "#3b82f6",
            strokeOpacity: 0.3,
            strokeWeight: 10,
            map
        });
        // Inner actual line
        new google.maps.Polyline({
            path: pathCoordinates,
            geodesic: true,
            strokeColor: "#2563eb",
            strokeOpacity: 1.0,
            strokeWeight: 3,
            map
        });
    }

    // ---------- HELPER TO CREATE HTML MARKERS ----------
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

        // Active/Pulse marker for current agent position
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
            const date = new Date(pathCoordinates[i].recorded_at.replace(' ', 'T'));
            const hour = date.getHours();

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

    // ---------- END OR CURRENT LOCATION ----------
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

    // ---------- MODERN INFOWINDOW ----------
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

<!-- Dependent Dropdowns (District/City/Tehsil/Pincode) -->
<script>
    const urls = {
        districts: "{!! url('admin/get-districts') !!}/",
        cities: "{!! url('admin/get-cities') !!}/",
        tehsils: "{!! url('admin/get-tehsils') !!}/",
        pincodes: "{!! url('admin/get-pincodes') !!}/"
    };
    $(function() {
        $('#state').on('change', function() {
            
            let id = $(this).val();
            if (id) $.get(urls.districts + id).done(d => fillOptions('#district', d));
        });
        $('#district').on('change', function() {
            let id = $(this).val();
            if (id) $.get(urls.cities + id).done(d => fillOptions('#city', d));
        });
        $('#city').on('change', function() {
            let id = $(this).val();
            if (id) {
                $.get(urls.tehsils + id).done(d => fillOptions('#tehsil', d));
                $.get(urls.pincodes + id).done(d => fillOptions('#pincode', d, 'pincode'));
            }
        });
    });

    function fillOptions(selector, data, label = 'name') {
        let opts = '<option value="">Select</option>';
        data.forEach(o => opts += `<option value="${o.id}">${o[label]}</option>`);
        $(selector).html(opts);
    }


    $(document).ready(function() {
        const stateId = "{{ old('state_id', $user->state_id ?? '') }}";
        const districtId = "{{ old('district_id', $user->district_id ?? '') }}";
        const cityId = "{{ old('city_id', $user->city_id ?? '') }}";
        const tehsilId = "{{ old('tehsil_id', $user->tehsil_id ?? '') }}";
        const pincodeId = "{{ old('pincode_id', $user->pincode_id ?? '') }}";

        if (stateId) {
            $.get(urls.districts + stateId).done(function(data) {
                fillOptions('#district', data);
                $('#district').val(districtId);

                if (districtId) {
                    $.get(urls.cities + districtId).done(function(data) {
                        fillOptions('#city', data);
                        $('#city').val(cityId);

                        if (cityId) {
                            $.get(urls.tehsils + cityId).done(function(data) {
                                fillOptions('#tehsil', data);
                                $('#tehsil').val(tehsilId);
                            });

                            $.get(urls.pincodes + cityId).done(function(data) {
                                fillOptions('#pincode', data, 'pincode');
                                $('#pincode').val(pincodeId);
                            });
                        }
                    });
                }
            });
        }
    });
</script>

<!-- Company > Executive Dropdown Linkage -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const cSelect = document.getElementById('company_id');
        const eSelect = document.getElementById('user_id');
        if (!cSelect || !eSelect) return;
        cSelect.addEventListener('change', () => {
            fetch(`/admin/companies/${cSelect.value}/executives`)
                .then(r => r.json()).then(d => {
                    eSelect.innerHTML = '<option value="">-- Select Executive --</option>';
                    d.executives.forEach(e => {
                        let opt = document.createElement('option');
                        opt.value = e.id;
                        opt.textContent = e.name;
                        eSelect.appendChild(opt);
                    });
                });
        });
    });
</script>

<!-- Travel Mode / Purpose / Tour Type Dropdown Loader -->
<script>
    const baseUrl = "{{ url('admin') }}";

    function loadDropdown(type, id, selected = null) {
        $.get(baseUrl + "/dropdown-values/" + type).done(r => {
            if (r.status === 'success') {
                let dd = $('#' + id).empty().append('<option value="">-- Select --</option>');
                r.values.forEach(v => dd.append(
                    `<option value="${v}" ${v==selected?'selected':''}>${v}</option>`));
            }
        });
    }
    // $(function() {
    //     @if(!isset($trip))
    //     loadDropdown('travel_mode', 'travel_mode');
    //     loadDropdown('purpose', 'purpose');
    //     loadDropdown('tour_type', 'tour_type');
    //     @else
    //     loadDropdown('travel_mode', 'travel_mode', "{{ old('travel_mode', $trip->travel_mode) }}");
    //     loadDropdown('purpose', 'purpose', "{{ old('purpose', $trip->purpose) }}");
    //     loadDropdown('tour_type', 'tour_type', "{{ old('tour_type', $trip->tour_type) }}");
    //     @endif
    // });
</script>

<!-- Select All Checkbox Control -->
<script>
    document.getElementById('select-all')?.addEventListener('change', function() {
        document.querySelectorAll('.customer-checkbox').forEach(cb => cb.checked = this.checked);
    });
</script>

<!-- Deny Reason Toggle -->
<script>
    function toggleReasonField() {
        document.getElementById('denial-reason-block').style.display =
            (document.getElementById('approval_status').value === 'denied') ? 'block' : 'none';
    }
</script>



<script>
    document.addEventListener("DOMContentLoaded", function() {
    const modalElement = document.getElementById("sessionHistoryModal");
    if (!modalElement) return; // stop if modal not on page

    const modal = new bootstrap.Modal(modalElement);
    const modalContent = document.getElementById("sessionHistoryContent");
    const modalTitle = document.getElementById("sessionHistoryModalLabel");

    document.body.addEventListener("click", function(e) {
        if (e.target.classList.contains("view-sessions-link")) {
            e.preventDefault();
            const userId = e.target.getAttribute("data-user-id");
            const userName = e.target.getAttribute("data-user-name");

            modalTitle.innerText = `Session History - ${userName}`;
            modalContent.innerHTML = "Loading...";

            fetch(`/admin/users/${userId}/sessions`)
                .then(response => {
                    if (!response.ok) throw new Error("Network error");
                    return response.text();
                })
                .then(data => modalContent.innerHTML = data)
                .catch(() => modalContent.innerHTML =
                    "<p class='text-danger'>Failed to load session history.</p>"
                );

            modal.show();
        }
    });
});

</script>
<script>
    @if(Session::has('success'))
        toastr.success("{{ Session::get('success') }}");
    @endif

    @if(Session::has('error'))
        toastr.error("{{ Session::get('error') }}");
    @endif

    @if(Session::has('info'))
        toastr.info("{{ Session::get('info') }}");
    @endif

    @if(Session::has('warning'))
        toastr.warning("{{ Session::get('warning') }}");
    @endif
</script>
@stack('scripts')