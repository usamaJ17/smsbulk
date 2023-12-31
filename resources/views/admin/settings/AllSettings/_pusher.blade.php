<div class="col-md-6 col-12">
    <div class="form-body">

        <div class="col-12">
            <p>{!! __('locale.description.pusher') !!} {{config('app.name')}}</p>
        </div>

        <form class="form form-vertical" action="{{ route('admin.settings.pusher') }}" method="post">
            @csrf

            <div class="col-12">
                <div class="mb-1">
                    <label for="broadcast_driver" class="form-label required">{{__('locale.settings.broadcast_driver')}}</label>
                    <select class="form-select" id="broadcast_driver" name="broadcast_driver">
                        <option value="log" @if(config('broadcasting.default') === 'log') selected @endif>Log</option>
                        <option value="pusher" @if(config('broadcasting.default') === 'pusher') selected @endif>Pusher</option>
                    </select>
                </div>
                @error('broadcast_driver')
                <p><small class="text-danger">{{ $message }}</small></p>
                @enderror
            </div>

            <div class="pusher">
                <div class="col-12">
                    <div class="mb-1">
                        <label for="app_id" class="form-label required">APP ID</label>
                        <input type="text" id="app_id" name="app_id" class="form-control" value="{{ config('broadcasting.connections.pusher.app_id') }}">
                        @error('app_id')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                        @enderror
                    </div>
                </div>

                <div class="col-12">
                    <div class="mb-1">
                        <label for="app_key" class="form-label required">Key</label>
                        <input type="text" id="app_key" name="app_key" class="form-control" value="{{ config('broadcasting.connections.pusher.key') }}">
                        @error('app_key')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                        @enderror
                    </div>
                </div>

                <div class="col-12">
                    <div class="mb-1">
                        <label for="app_secret" class="form-label required">Secret</label>
                        <input type="text" id="app_secret" name="app_secret" class="form-control" value="{{ config('broadcasting.connections.pusher.secret') }}">
                        @error('app_secret')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                        @enderror
                    </div>
                </div>

                <div class="col-12">
                    <div class="mb-1">
                        <label for="app_cluster" class="form-label required">Cluster</label>
                        <input type="text" id="app_cluster" name="app_cluster" class="form-control" value="{{ env('PUSHER_APP_CLUSTER') }}">
                        @error('app_cluster')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                        @enderror
                    </div>
                </div>
            </div>
            <div class="col-12 mt-2">
                <button type="submit" class="btn btn-primary mb-1">
                    <i data-feather="save"></i> {{__('locale.buttons.save')}}
                </button>
            </div>


        </form>
    </div>
</div>
