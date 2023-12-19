@php use App\Helpers\Helper;use App\Library\Tool; @endphp
        <!-- Chat Sidebar area -->
<div class="sidebar-content">
    <span class="sidebar-close-icon">
        <i data-feather="x"></i>
    </span>


    <!-- Sidebar header start -->
    <div class="chat-fixed-search">
        <div class="d-flex align-items-center w-100">
            <input type="checkbox" id="bulk-select-checkbox">
            <div class="input-group input-group-merge ms-1 w-100">
                <span class="input-group-text round"><i data-feather="search" class="text-muted"></i></span>
                <input type="text" class="form-control round search_chat_new" id="chat-search" placeholder="{{ __('locale.labels.search') }}">
            </div>
            <div class="d-flex align-items-center">
                <span class="b_del_btn" data-bs-toggle="tooltip" data-bs-placement="top" title="Bulk Delete"><i data-feather="trash" class="cursor-pointer font-medium-2 text-danger"></i></span>
                <span class="s_del_btn" style="margin-left: 10px;" data-bs-toggle="tooltip" data-bs-placement="top" title="Text Search"><i data-feather="search" class="cursor-pointer font-medium-2 text-success"></i></span>
                @if($trash)
                    <a href="{{ route('customer.chatbox.index') }}" class="btn btn-sm btn-success"  style="margin-left: 10px;" data-bs-toggle="tooltip" data-bs-placement="top" title="View All Chats">All</a>
                @else
                    <a href="{{ route('customer.chatbox.trash') }}" class="btn btn-sm btn-danger"  style="margin-left: 10px;" data-bs-toggle="tooltip" data-bs-placement="top" title="View Deleted Chats">Trash</a>
                @endif
                
            </div>
            <div class="d-block d-md-none">
                <a href="{{ route('customer.chatbox.new') }}" class="text-dark ms-1"><i data-feather="plus-circle"></i> </a>
            </div>
        </div>
    </div>
            <div class="justify-content-between mt-1 mb-1">
                <div class="row" style="margin-left: 8px;">
                    <div class="col-md-4">
                        @if ($chat_box->previousPageUrl())
                            <a href="{{ $chat_box->previousPageUrl() }}" class="btn btn-outline-primary">
                                <i data-feather="chevron-left"></i> Back
                            </a>
                        @else
                            <a href="#" class="btn btn-outline-primary">
                                First
                            </a>
                        @endif
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted">Page {{ $chat_box->currentPage() }} of {{ $chat_box->lastPage() }}</span>
                    </div>
                    <div class="col-md-4">
                        @if ($chat_box->nextPageUrl())
                            <a href="{{ $chat_box->nextPageUrl() }}" class="btn btn-outline-primary">
                                Next <i data-feather="chevron-right"></i>
                            </a>
                        @else
                            <a href="#" class="btn btn-outline-primary">
                                Last
                            </a>
                        @endif
                    </div>
                </div>
            </div>
    <!-- Sidebar header end -->

    <!-- Sidebar Users start -->
    <div id="users-list" class="chat-user-list-wrapper list-group">
        <ul class="chat-users-list chat-list media-list">
            @foreach($chat_box as $chat)
                <li data-id="{{$chat->uid}}" class="back_fil"  data-box-id="{{$chat->id}}">
                        <div class="checkbox-wrapper">
                            <input type="checkbox" class="chat-checkbox-bulk" data-chat-id="{{$chat->id}}">
                        </div>
                        <span class="avatar">
                            <img src="{{asset('images/profile/profile.jpg')}}" height="42" width="42" alt="Avatar"/>
                        </span>
                    <div class="chat-info flex-grow-1">
                        <h5 class="mb-0">{{ $chat->to }}</h5>
                        <p class="card-text text-truncate" id="cb_{{$chat->uid}}">
                            {{ $chat->from }}
                        </p>
                        <p class="card-text text-truncate">
                            {{ $chat->preview_msg }}
                        </p>
                    </div>
                    <div class="chat-meta text-nowrap">
                        <small class="float-end mb-25 chat-time">{{ Tool::customerDateTime($chat->updated_at) }}</small>
                        <p class="float-end mb-25 chat-time"><b>{{ $chat->c_name }}</b></p>
                        @if($chat->notification)
                            <span class="badge bg-primary rounded-pill float-end notification_count">{{ $chat->notification }}</span>
                        @else
                            <div class="counter" hidden>
                                <span class="badge bg-primary rounded-pill float-end notification_count"></span>
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach

        </ul>
    </div>

    
    <!-- Sidebar Users end -->
</div>
<!--/ Chat Sidebar area -->
