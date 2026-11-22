@php
    $mapFarm = $farm ?? null;
    $mapEditingFarm = $mapFarm !== null;
    $mapPrefix = $prefix ?? 'farm-location';
    $mapValue = $fieldValue ?? fn (string $key, mixed $fallback = null) => old($key, $fallback);
    $mapSavedOutline = $mapEditingFarm && $mapFarm->boundary_coordinates
        ? json_encode($mapFarm->boundary_coordinates)
        : $mapValue('boundary_coordinates', '');
    $mapRequiredPin = $requiredPin ?? false;
    $mapId = $mapId ?? $mapPrefix.'-map';
@endphp

<fieldset id="{{ $fieldsetId ?? $mapPrefix.'-location' }}" class="farmer-field ui-farm-pin-field sm:col-span-2 {{ $mapEditingFarm ? '' : 'public-registration-map' }}" data-farm-location data-location-enabled="{{ ($farmLocationsReady ?? true) ? 'true' : 'false' }}" data-boundary-enabled="{{ ($farmBoundaryReady ?? false) ? 'true' : 'false' }}" data-location-search-url="{{ route('farm-location.search') }}">
    <legend>Pin your farm on the Bongabong map</legend>
    <div class="ui-farm-map-intro">
        <span class="ui-farm-map-location"><x-ui.icon name="location" size="sm"/> Bongabong, Oriental Mindoro</span>
        <p>Find a public sitio or landmark, then check the suggested pin on the map. You can move it to the exact farm entrance or area. The pin is a location reference, not a land survey.</p>
    </div>
    <div class="ui-farm-location-search">
        <button class="farmer-button farmer-button-light" type="button" data-farm-location-search @disabled(!($farmLocationsReady ?? true))>
            <x-ui.icon name="search" size="sm"/><span data-farm-location-search-label>Find this place on the map</span>
        </button>
        <p class="ui-farm-location-search-note">Search runs only when you press this button. It sends this farm's sitio or landmark and barangay to OpenStreetMap Nominatim. Do not enter your name, contact details, or a private address. For a private or unmapped place, use the map, GPS, or coordinates instead.</p>
        <p class="ui-farm-location-search-status" data-farm-location-search-status role="status" aria-live="polite"></p>
        <ul class="ui-farm-location-search-results" data-farm-location-search-results aria-label="Matching places inside Bongabong" hidden></ul>
    </div>
    <div class="ui-farm-map-tools" aria-label="Farm map tools">
        <button class="farmer-button farmer-button-light is-selected" type="button" data-farm-map-mode="pin" aria-pressed="true">Place farm pin</button>
        <button class="farmer-button farmer-button-light" type="button" data-farm-map-mode="boundary" aria-pressed="false" @disabled(!($farmBoundaryReady ?? false))>Draw farm outline</button>
        <button class="farmer-button farmer-button-light" type="button" data-farm-boundary-undo disabled>Undo point</button>
        <button class="farmer-button farmer-button-light" type="button" data-farm-boundary-save disabled>Save outline</button>
        <button class="farmer-button farmer-button-light" type="button" data-farm-boundary-clear @disabled(!($farmBoundaryReady ?? false))>Clear outline</button>
    </div>
    @if($farmBoundaryReady ?? false)
        <div class="ui-farm-boundary-coordinate-controls" data-farm-boundary-manual-controls hidden>
            <div class="farmer-field"><label for="{{ $mapPrefix }}-boundary-latitude">Outline point latitude</label><input id="{{ $mapPrefix }}-boundary-latitude" type="number" step="0.0000001" min="-90" max="90" inputmode="decimal" data-farm-boundary-latitude></div>
            <div class="farmer-field"><label for="{{ $mapPrefix }}-boundary-longitude">Outline point longitude</label><input id="{{ $mapPrefix }}-boundary-longitude" type="number" step="0.0000001" min="-180" max="180" inputmode="decimal" data-farm-boundary-longitude></div>
            <button class="farmer-button farmer-button-light" type="button" data-farm-boundary-add-point>Add coordinate to outline</button>
        </div>
    @endif
    <div id="{{ $mapId }}" class="ui-farm-map" data-farm-map data-google-key="{{ config('services.google_maps.browser_key') }}" data-bongabong-boundary="{{ base64_encode(json_encode($bongabongBoundary ?? [])) }}" tabindex="0" role="region" aria-label="Google map of Bongabong. Click to place a farm pin or draw the farm outline." data-default-latitude="12.74642" data-default-longitude="121.48741"></div>
    <input type="hidden" name="boundary_coordinates" value="{{ $mapValue('boundary_coordinates', $mapSavedOutline) }}" data-farm-boundary-coordinates @disabled(!($farmBoundaryReady ?? false))>
    <p class="ui-farm-boundary-status" data-farm-boundary-status role="status" aria-live="polite">{{ $mapSavedOutline ? 'Saved outline shown. It is a farmer-provided planning shape, not a land survey.' : 'Farm outline is optional. Trace at least three points and save it if you want the MAO to see an approximate farm area.' }}</p>
    @if(!($farmBoundaryReady ?? false))
        <div class="farmer-message farmer-message-warning ui-farm-boundary-notice" role="status">Farm outlines are not enabled in this database. You can still save the map pin.</div>
    @endif
    @if(($showFarmErrors ?? false))
        @error('boundary_coordinates')<p class="ui-field-error" role="alert">{{ $message }}</p>@enderror
        @error('farm_location')<p class="ui-field-error" role="alert">{{ $message }}</p>@enderror
    @endif
    <p class="ui-farm-map-message" data-farm-map-message role="status" aria-live="polite" hidden></p>
    <noscript><p class="farmer-message farmer-message-warning">The map needs JavaScript to place a pin by clicking. You can still enter the latitude and longitude below.</p></noscript>
    <p class="ui-farm-map-attribution">The map is provided by Google Maps. Place-search data &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a> (ODbL). Approximate Bongabong outline: geoBoundaries 2020, sourced from NAMRIA, PSA and OCHA Philippines (CC BY 3.0 IGO); not a legal or cadastral boundary. A place search sends only the farm landmark and barangay after you press Find.</p>
    @if(!($farmLocationsReady ?? true))
        <div class="farmer-message farmer-message-warning" role="status"><strong>Pin saving is not active.</strong> The map can be viewed, but this database cannot yet save farm coordinates.</div>
    @endif
    <div class="ui-farm-pin-controls">
        <button class="farmer-button farmer-button-light" type="button" data-farm-use-location @disabled(!($farmLocationsReady ?? true))><x-ui.icon name="location" size="sm"/> Use my current location</button>
        <button class="farmer-button farmer-button-light" type="button" data-farm-clear-pin @disabled(!($farmLocationsReady ?? true))>Remove pin</button>
        @if($mapEditingFarm && $mapFarm->latitude !== null && $mapFarm->longitude !== null)
            <a class="ui-farm-map-link" data-farm-map-link href="https://www.google.com/maps/search/?api=1&amp;query={{ $mapFarm->latitude }},{{ $mapFarm->longitude }}" target="_blank" rel="noopener noreferrer">Open saved pin in Google Maps</a>
        @else
            <a class="ui-farm-map-link" data-farm-map-link data-farm-google-map-link hidden target="_blank" rel="noopener noreferrer">Open selected pin in Google Maps</a>
        @endif
    </div>
    <p class="ui-farm-pin-status" data-farm-location-status role="status" aria-live="polite">{{ $mapEditingFarm && $mapFarm->latitude !== null && $mapFarm->longitude !== null ? 'Saved farm pin. The MAO has not verified this location.' : (($farmLocationsReady ?? true) ? 'Click the map to place your farm pin. You can also enter coordinates below.' : 'Map preview only. Pin selection and saving are disabled until the database is updated.') }}</p>
    <div class="grid gap-3 sm:grid-cols-2">
        <div class="farmer-field"><label for="{{ $mapPrefix }}-latitude">Latitude @if($mapRequiredPin && ($farmLocationsReady ?? true))<span aria-hidden="true">*</span>@endif</label><input id="{{ $mapPrefix }}-latitude" name="latitude" type="number" step="0.0000001" min="-90" max="90" inputmode="decimal" value="{{ $mapValue('latitude', $mapEditingFarm ? $mapFarm->latitude : '') }}" data-farm-latitude autocomplete="off" @required($mapRequiredPin && ($farmLocationsReady ?? true)) @disabled(!($farmLocationsReady ?? true)) @if(($showFarmErrors ?? false) && $errors->has('latitude')) aria-invalid="true" aria-describedby="{{ $mapPrefix }}-latitude-error" @endif>@if(($showFarmErrors ?? false))@error('latitude')<small id="{{ $mapPrefix }}-latitude-error" class="ui-field-error">{{ $message }}</small>@enderror @endif</div>
        <div class="farmer-field"><label for="{{ $mapPrefix }}-longitude">Longitude @if($mapRequiredPin && ($farmLocationsReady ?? true))<span aria-hidden="true">*</span>@endif</label><input id="{{ $mapPrefix }}-longitude" name="longitude" type="number" step="0.0000001" min="-180" max="180" inputmode="decimal" value="{{ $mapValue('longitude', $mapEditingFarm ? $mapFarm->longitude : '') }}" data-farm-longitude autocomplete="off" @required($mapRequiredPin && ($farmLocationsReady ?? true)) @disabled(!($farmLocationsReady ?? true)) @if(($showFarmErrors ?? false) && $errors->has('longitude')) aria-invalid="true" aria-describedby="{{ $mapPrefix }}-longitude-error" @endif>@if(($showFarmErrors ?? false))@error('longitude')<small id="{{ $mapPrefix }}-longitude-error" class="ui-field-error">{{ $message }}</small>@enderror @endif</div>
    </div>
    <input type="hidden" name="location_source" value="{{ $mapValue('location_source', $mapEditingFarm ? $mapFarm->location_source : '') }}" data-farm-location-source @disabled(!($farmLocationsReady ?? true))>
    <input type="hidden" name="location_accuracy_meters" value="{{ $mapValue('location_accuracy_meters', $mapEditingFarm ? $mapFarm->location_accuracy_meters : '') }}" data-farm-location-accuracy @disabled(!($farmLocationsReady ?? true))>
</fieldset>
