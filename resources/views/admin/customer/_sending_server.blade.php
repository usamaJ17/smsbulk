<div class="card">
    <div class="card-body py-2 my-25">
        <div class="row">
            <div class="col-md-2 col-12 vertical-modal-ex">
                <button class="btn btn-primary btn-sm pull-right" data-bs-toggle="modal" data-bs-target="#addSendingSever">
                    <i data-feather="plus-circle"></i> {{__('locale.sending_servers.add_sending_server')}}
                </button>


                {{-- Modal --}}
                <div class="modal fade" id="addSendingSever" tabindex="-1" role="dialog" aria-labelledby="addSendingSever" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h4 class="modal-title" id="myModalLabel33">{{ __('locale.sending_servers.add_sending_server') }} </h4>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form action="{{ route('admin.customers.sending-server', $customer->uid) }}" method="post">
                                @csrf
                                <div class="modal-body">

                                    <label for="sending_servers" class="required">{{__('locale.sending_servers.select_sending_server')}}</label>
                                    <div class="mb-1">

                                        <select data-placeholder="{{ __('locale.labels.choose_your_option') }}" class="form-select select2" id="sending_servers" name="sending_servers[]" multiple>
                                            @if($sending_servers->count())
                                                @foreach($sending_servers as $server)
                                                    <option value="{{$server->id}}"> {{ $server->name }}</option>
                                                @endforeach
                                            @else
                                                <option>{{ __('locale.sending_servers.have_no_sending_server_to_add') }}</option>
                                            @endif
                                        </select>
                                        @error('sending_servers')
                                        <p><small class="text-danger">{{ $message }}</small></p>
                                        @enderror
                                    </div>

                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">{{__('locale.buttons.close')}}</button>
                                    <button type="submit" class="btn btn-primary btn-sm">{{__('locale.labels.choose')}}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>


            {{-- DataTable starts --}}
            <div class="table-responsive mt-4">
                <table class="table data-list-view sending-server">
                    <tbody>

                    @if($customer->sendingServers()->count() > 0)
                        @foreach ($customer->sendingServers as $customerSendingServer)
                            @if(! empty($customerSendingServer->sendingServer))
                                <tr>
                                    <td>
                                        <div>
                                            <p class="fw-bold">{{ $customerSendingServer->sendingServer->name }}</p>
                                            <p class="text-muted">{{__('locale.sending_servers.sending_limit')}} {!! $customerSendingServer->sendingServer->displayQuotaHtml() !!}</p>
                                        </div>
                                    </td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.sending-servers.show', $customerSendingServer->sendingServer->uid) }}" class="text-primary me-1"><i data-feather="edit" class="feather-20"></i></a>
                                        <span class="text-danger cursor-pointer action-sending-server-delete" data-id="{{ $customerSendingServer->sendingServer->id }}"><i data-feather="trash" class="feather-20"></i></span>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    @else
                        <p class="text-center text-highlight fw-bold text-danger"><i data-feather="menu"></i> {{ __('locale.sending_servers.have_no_sending_server') }}</p>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
