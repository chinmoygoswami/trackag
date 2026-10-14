@extends('admin.layout.layout')

@section('title', 'Live Tracking | Trackag')

@push('styles')
<style>
    .live-user-photo-marker {
        position: absolute;
        width: 48px;
        height: 48px;
        border: 3px solid #fff;
        border-radius: 50%;
        overflow: visible;
        transform: translate(-50%, -100%);
        box-shadow: 0 2px 7px rgba(0, 0, 0, .4);
        cursor: pointer;
        background: #fff;
    }

    .live-user-photo-marker::after {
        content: '';
        position: absolute;
        left: 50%;
        bottom: -10px;
        transform: translateX(-50%);
        border-left: 8px solid transparent;
        border-right: 8px solid transparent;
        border-top: 10px solid #fff;
    }

    .live-user-photo-marker img {
        display: block;
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
    }
</style>
@endpush

@section('content')
<main class="app-main">

    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">User Live Tracking</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card card-primary card-outline">
                <div class="card-body">
                    <div class="row mb-3"> 
                        <div class="col-md-3"> 
                        <label>State</label> 
                        <select id="stateFilter" class="form-control"> 
                            <option value="">All State</option> 
                            @foreach($states as $state) 
                            <option value="{{ $state->id }}">{{ $state->name }}</option> 
                            @endforeach 
                        </select> 
                    </div> 
                    <div class="col-md-3"> 
                        <label>User</label> 
                        <select id="userFilter" class="form-control"> 
                            <option value="">All User</option> 
                            @foreach($users as $user) 
                            <option value="{{ $user->id }}">{{ $user->name }}</option> 
                            @endforeach 
                        </select> 
                    </div> 
                </div>
                    <div id="mapNew" style="height:600px; width:100%;" class="rounded border"></div>
                </div>
            </div>
        </div>
    </div>

</main>
@endsection

@push('scripts')

<script>

var mapNew;
var markersNew = [];
var liveInfoWindow;
var LiveUserPhotoOverlay;

function defineLiveUserPhotoOverlay() {
    LiveUserPhotoOverlay = class extends google.maps.OverlayView {
        constructor(user) {
            super();
            this.position = new google.maps.LatLng(
                parseFloat(user.latitude),
                parseFloat(user.longitude)
            );
            this.element = document.createElement('div');
            this.element.className = 'live-user-photo-marker';
            this.element.title = user.name || 'Unknown';

            const image = document.createElement('img');
            image.src = user.image_url;
            image.alt = user.name || 'User photo';
            image.onerror = function () {
                this.onerror = null;
                this.src = "{{ asset('admin/images/avatar.png') }}";
            };
            this.element.appendChild(image);

            this.element.addEventListener('click', (event) => {
                event.stopPropagation();
                liveInfoWindow.setContent(`
                    <div style="min-width:150px">
                        <strong>${user.name || 'Unknown'}</strong><br>
                        <small>Time : ${user.time || ''}</small>
                    </div>
                `);
                liveInfoWindow.setPosition(this.position);
                liveInfoWindow.open(mapNew);
            });

            this.setMap(mapNew);
        }

        onAdd() {
            this.getPanes().overlayMouseTarget.appendChild(this.element);
        }

        draw() {
            const point = this.getProjection().fromLatLngToDivPixel(this.position);
            if (!point) return;
            this.element.style.left = `${point.x}px`;
            this.element.style.top = `${point.y}px`;
        }

        onRemove() {
            this.element.remove();
        }
    };
}

function initMapNewSafe() {
    if (!window.google || !google.maps || !google.maps.OverlayView) {
        setTimeout(initMapNewSafe, 200);
        return;
    }

    defineLiveUserPhotoOverlay();

    mapNew = new google.maps.Map(document.getElementById("mapNew"), {
        zoom: 7,
        center: { lat: 23.0225, lng: 72.5714 }
    });
    liveInfoWindow = new google.maps.InfoWindow();

    loadLiveLocationsNew();

    setInterval(loadLiveLocationsNew, 15000);
}


function loadLiveLocationsNew() {

    let state_id = document.getElementById('stateFilter').value;
    let user_id  = document.getElementById('userFilter').value;

    fetch("{{ url('admin/tracking/live-data') }}?state_id="+state_id+"&user_id="+user_id)
        .then(response => response.json())
        .then(data => {

            markersNew.forEach(marker => marker.setMap(null));
            markersNew = [];

            data.forEach(user => {

                if (!user.latitude || !user.longitude) return;

                let marker = new LiveUserPhotoOverlay(user);
                markersNew.push(marker);

            });

        });
}


document.getElementById('stateFilter').addEventListener('change',function(){
    loadLiveLocationsNew();
});

document.getElementById('userFilter').addEventListener('change',function(){
    loadLiveLocationsNew();
});


window.addEventListener("load", function() {
    setTimeout(initMapNewSafe, 500);
});

</script>

@endpush
