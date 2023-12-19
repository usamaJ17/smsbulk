@extends('layouts/contentLayoutMaster')

@section('title', __('locale.menu.Chat Box'))

@section('page-style')
    <!-- Page css files -->
    <link rel="stylesheet" href="{{ asset(mix('css/base/pages/app-chat.css')) }}">
    <link rel="stylesheet" href="{{ asset(mix('css/base/pages/app-chat-list.css')) }}">
    <link rel="stylesheet" href="{{ asset(mix('vendors/css/extensions/sweetalert2.min.css')) }}">

    <style>
        /* For screens smaller than 576px */
        @media (max-width: 575.98px) {
            /* Set the max-width of the image or video to 100% to make it responsive */
            img, video {
                max-width: 100%;
                height: auto;
            }
        }

        /* For screens between 576px and 768px */
        @media (min-width: 576px) and (max-width: 767.98px) {
            img, video {
                max-width: 100%;
                height: auto;
            }
        }

        /* For screens between 768px and 992px */
        @media (min-width: 768px) and (max-width: 991.98px) {
            img, video {
                max-width: 100%;
                height: auto;
            }
        }

        /* For screens between 992px and 1200px */
        @media (min-width: 992px) and (max-width: 1199.98px) {
            img, video {
                max-width: 100%;
                height: auto;
            }
        }

        /* For screens larger than 1200px */
        @media (min-width: 1200px) {
            img, video {
                max-width: 100%;
                height: auto;
            }
        }

    </style>

@endsection

@section('content-sidebar')
    @include('customer.ChatBox._sidebar')
@endsection


@section('content')
    <div class="body-content-overlay"></div>
    <!-- Main chat area -->
    <section class="chat-app-window">
        <!-- To load Conversation -->
        <div class="start-chat-area">
            <div class="mb-1 start-chat-icon">
                <i data-feather="message-square"></i>
            </div>
            <h4 class="sidebar-toggle start-chat-text d-block d-md-none">
                {{ __('locale.labels.new_conversion') }}
            </h4>
            <h4 class="sidebar-toggle start-chat-text d-none d-md-block">
                <a href="{{ route('customer.chatbox.new') }}" class="text-dark">{{ __('locale.labels.new_conversion') }}</a>
            </h4>
        </div>
        <!--/ To load Conversation -->

        <!-- Active Chat -->
        <div class="active-chat d-none">
            <!-- Chat Header -->
            <div class="chat-navbar">
                <header class="chat-header">
                    <div class="d-flex align-items-center">
                        <div class="sidebar-toggle d-block d-lg-none me-1">
                            <i data-feather="menu" class="font-medium-5"></i>
                        </div>
                        <div class="avatar avatar-border user-profile-toggle m-0 me-1"></div>
                    </div>
                    <div class="d-flex align-items-center">
                        @if($trash)
                            <span class="restore-btn" data-bs-toggle="tooltip" data-bs-placement="top" title="Restore"><i data-feather="check" class="cursor-pointer font-medium-2 text-success"></i></span>
                        @else
                            <button class="btn btn-sm btn-warning" id="switch_num_btn"> Swap Number </button>
                            <span class="add-to-blacklist" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('locale.labels.block') }}"> <i data-feather="shield" class="cursor-pointer font-medium-2 mx-1 text-primary"></i> </span>
                            <span class="remove-btn" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('locale.buttons.delete') }}"><i data-feather="trash" class="cursor-pointer font-medium-2 text-danger"></i></span>
                        @endif

                    </div>
                </header>
            </div>
            <!--/ Chat Header -->

            <!-- User Chat messages -->
            <div class="user-chats">
                <div class="chats">
                    <div class="chat_history"></div>
                </div>
            </div>
            
            
            <!-- User Chat messages -->

            <!-- Submit Chat form -->
            <form class="chat-app-form" action="javascript:void(0);" onsubmit="enter_chat();">
                <div class="input-group input-group-merge me-1 form-send-message">
                    <input type="text" class="form-control message" placeholder="{{ __('locale.campaigns.type_your_message') }}"/>
                </div>
                    <input type="hidden" name='swap_num' value=0 id='swap_num'/>
                <button type="button" class="btn btn-primary send" onclick="enter_chat();">
                    <i data-feather="send" class="d-lg-none"></i>
                    <span class="d-none d-lg-block">{{ __('locale.buttons.send') }}</span>
                </button>
            </form>
            <!--/ Submit Chat form -->
        </div>
        <!--/ Active Chat -->
    </section>
    <!--/ Main chat area -->
@endsection

@section('page-script')
    <!-- Page js files -->
    <script src="{{ asset(mix('js/scripts/pages/chat.js')) }}"></script>
    <script src="{{ asset(mix('vendors/js/extensions/sweetalert2.all.min.js')) }}"></script>
    @if(config('broadcasting.connections.pusher.app_id'))
        <script src="{{ asset(mix('js/scripts/echo.js')) }}"></script>
    @endif

    <script>
        // autoscroll to bottom of Chat area
        let chatContainer = $(".user-chats"),
            details,
            chatHistory = $(".chat_history");

        $(".chat-users-list li").on("click", function () {
            chatHistory.empty();
            chatContainer.animate({scrollTop: chatContainer[0].scrollHeight}, 0)

            const chat_id = $(this).data('id');
            const trash = {{ json_encode($trash) }};
            var url = '';
            if(trash){
                url = `{{ url('/chat-box')}}/${chat_id}/messages/trash`;
            }else{
                url = `{{ url('/chat-box')}}/${chat_id}/messages`;
            }

            $.post(
                url,
                {_token: "{{csrf_token()}}"}
            )
                .done(function (response) {
                    let details = `<input type="hidden" value="${chat_id}" name="chat_id" class="chat_id">`;

                    const cwData = JSON.parse(response.data);

                    cwData.forEach((sms) => {
                        let media_url = '';
                        if (sms.media_url !== null) {
                            let fileType = isImageOrVideo(sms.media_url);
                            if (fileType === 'video') {
                                media_url = `<p><video src="${sms.media_url}" controls>Your browser does not support the video tag. <video/></p>`;
                            } else if (fileType === 'audio') {
                                media_url = `<p><audio src="${sms.media_url}" controls>Your browser does not support the audio element. </audio></p>`;
                            } else {
                                media_url = `<p><img src="${sms.media_url}" alt=""/></p>`;
                            }
                        }

                        let message = '';
                        if (sms.message !== null) {
                            if(sms.message.startsWith("https://")){
                                message = `<p><img src="${sms.message}" alt=""/></p>`;
                            }else{
                                message = `<p>${sms.message}</p>`;   
                            }
                        }

                        const chatHtml = `<div class="chat ${sms.send_by === 'to' ? 'chat-left' : ''}">
                        
                    <div class="chat-avatar">
                        <span class="avatar box-shadow-1 cursor-pointer">
                            <img src="{{asset('images/profile/profile.jpg')}}" alt="avatar" height="36" width="36"/>
                        </span>
                    </div>
                    <div class="chat-body">
                        <div class="chat-content">
                            ${media_url}
                            ${message}
                        </div>
                    </div>
                </div>`;

                        details += chatHtml;
                    });

                    chatHistory.append(details);
                    chatContainer.animate({scrollTop: chatContainer[0].scrollHeight}, 400)
                })
                .fail(function (xhr, status, error) {
                    console.log(error);
                });
        });


        function isImageOrVideo(url) {
            const ext = url.substr(url.lastIndexOf('.') + 1);
            const imageExts = ['jpg', 'jpeg', 'gif', 'png'];
            const videoExts = ['mp4', 'avi', 'mov', 'wmv'];
            const audioExts = ['ogg', 'mp3'];

            if (imageExts.indexOf(ext.toLowerCase()) !== -1) {
                return 'image';
            } else if (videoExts.indexOf(ext.toLowerCase()) !== -1) {
                return 'video';
            } else if (audioExts.indexOf(ext.toLowerCase()) !== -1) {
                return 'audio';
            } else {
                return false;
            }
        }
        function add_to_chat(source, def_message = null) {
            
            let message = $(".message"),
                chatBoxId = $(".chat_id").val();
                 
                if(!def_message){
                    messageValue = message.val();
                }else{
                    messageValue = def_message;
                }
                $(".message").val(messageValue);
        }
        // Add message to chat
        function enter_chat() {
            let message = $(".message"),
                chatBoxId = $(".chat_id").val(),
                swap_num = $("#swap_num").val(),
                messageValue = message.val();

            $.ajax({
                url: "{{ url('/chat-box')}}" + '/' + chatBoxId + '/reply',
                type: "POST",
                data: {
                    message: messageValue,
                    swap_num: swap_num,
                    _token: "{{csrf_token()}}"
                },
                success: function (response) {

                    if (response.status === 'success') {
                        toastr['success'](response.message, 'Success!!', {
                            closeButton: true,
                            positionClass: 'toast-top-right',
                            progressBar: true,
                            newestOnTop: true,
                            rtl: isRtl
                        });

                        let html = '<div class="chat">' +
                            '<div class="chat-avatar">' +
                            '<span class="avatar box-shadow-1 cursor-pointer">' +
                            '<img src="{{ asset('images/profile/profile.jpg') }}" alt="avatar" height="36" width="36"/>' +
                            '</span>' +
                            '</div>' +
                            '<div class="chat-body">' +
                            '<div class="chat-content">' +
                            '<p>' + messageValue + '</p>' +
                            '</div>' +
                            '</div>' +
                            '</div>';
                        chatHistory.append(html);
                        message.val("");
                        $(".user-chats").scrollTop($(".user-chats > .chats").height());
                    } else {
                        if (response.hasOwnProperty('type_code') && response.type_code == 'sim_emp') {
                            Swal.fire({
                              title: "This number no longer exist, Do you want to continue chat with another number?",
                              text: "You won't be able to revert this!",
                              icon: "warning",
                              showCancelButton: true,
                              confirmButtonColor: "#3085d6",
                              cancelButtonColor: "#d33",
                              confirmButtonText: "Yes, swap number!"
                            }).then((result) => {
                              if (result.isConfirmed) {
                                $("#swap_num").val(1);
                                enter_chat();
                              }
                            });
                        }else{
                            toastr['warning'](response.message, "{{ __('locale.labels.attention') }}", {
                                closeButton: true,
                                positionClass: 'toast-top-right',
                                progressBar: true,
                                newestOnTop: true,
                                rtl: isRtl
                            });   
                        }
                    }
                },
                error: function (reject) {
                    if (reject.status === 422) {
                        let errors = reject.responseJSON.errors;
                        $.each(errors, function (key, value) {
                            toastr['warning'](value[0], "{{__('locale.labels.attention')}}", {
                                closeButton: true,
                                positionClass: 'toast-top-right',
                                progressBar: true,
                                newestOnTop: true,
                                rtl: isRtl
                            });
                        });
                    } else {
                        toastr['warning'](reject.responseJSON.message, "{{__('locale.labels.attention')}}", {
                            closeButton: true,
                            positionClass: 'toast-top-right',
                            progressBar: true,
                            newestOnTop: true,
                            rtl: isRtl
                        });
                    }
                }
            });


        }
        function swap_num() {
            let chatBoxId = $(".chat_id").val();
            $.ajax({
                url: "{{ url('/chat-box')}}" + '/' + chatBoxId + '/swap_num',
                type: "POST",
                data: {
                    _token: "{{csrf_token()}}"
                },
                success: function (response) {
                    if (response.status === 'success') {
                        toastr['success']('Number Swapped', 'Success!!', {
                            closeButton: true,
                            positionClass: 'toast-top-right',
                            progressBar: true,
                            newestOnTop: true,
                            rtl: isRtl
                        });
                        $('#cb_'+chatBoxId).text(response.phone);
                        // setTimeout(function () {
                        //     window.location.reload(); // then reload the page.(3)
                        // }, 3000);

                    } else {
                        toastr['warning'](response.message, '{{ __('locale.labels.warning') }}!', {
                            closeButton: true,
                            positionClass: 'toast-top-right',
                            progressBar: true,
                            newestOnTop: true,
                            rtl: isRtl
                        });
                    }
                }
            }); 
        }

        
        $('#switch_num_btn').on('click',function(){
            Swal.fire({
              title: "Do you want to continue chat with another number?",
              text: "You won't be able to revert this!",
              icon: "warning",
              showCancelButton: true,
              confirmButtonColor: "#3085d6",
              cancelButtonColor: "#d33",
              confirmButtonText: "Yes, swap number!"
            }).then((result) => {
              if (result.isConfirmed) {
                $("#swap_num").val(1);
                swap_num();
              }
            });
        });


        $(".remove-btn").on('click', function (event) {
            event.preventDefault();
            let sms_id = $(".chat_id").val();

            Swal.fire({
                title: "{{ __('locale.labels.are_you_sure') }}",
                text: "{{ __('locale.labels.able_to_revert') }}",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: "{{ __('locale.labels.delete_it') }}",
                customClass: {
                    confirmButton: 'btn btn-primary',
                    cancelButton: 'btn btn-outline-danger ms-1'
                },
                buttonsStyling: false,
            }).then(function (result) {
                if (result.value) {
                    $.ajax({
                        url: "{{ url('/chat-box')}}" + '/' + sms_id + '/delete',
                        type: "POST",
                        data: {
                            _token: "{{csrf_token()}}"
                        },
                        success: function (response) {

                            if (response.status === 'success') {
                                toastr['success'](response.message, '{{__('locale.labels.success')}}!!', {
                                    closeButton: true,
                                    positionClass: 'toast-top-right',
                                    progressBar: true,
                                    newestOnTop: true,
                                    rtl: isRtl
                                });

                                setTimeout(function () {
                                    window.location.reload(); // then reload the page.(3)
                                }, 3000);

                            } else {
                                toastr['warning'](response.message, '{{ __('locale.labels.warning') }}!', {
                                    closeButton: true,
                                    positionClass: 'toast-top-right',
                                    progressBar: true,
                                    newestOnTop: true,
                                    rtl: isRtl
                                });
                            }
                        },
                        error: function (reject) {
                            if (reject.status === 422) {
                                let errors = reject.responseJSON.errors;
                                $.each(errors, function (key, value) {
                                    toastr['warning'](value[0], "{{__('locale.labels.attention')}}", {
                                        closeButton: true,
                                        positionClass: 'toast-top-right',
                                        progressBar: true,
                                        newestOnTop: true,
                                        rtl: isRtl
                                    });
                                });
                            } else {
                                toastr['warning'](reject.responseJSON.message, "{{__('locale.labels.attention')}}", {
                                    closeButton: true,
                                    positionClass: 'toast-top-right',
                                    progressBar: true,
                                    newestOnTop: true,
                                    rtl: isRtl
                                });
                            }
                        }
                    });
                }
            })

        })
        $(".restore-btn").on('click', function (event) {
            event.preventDefault();
            let sms_id = $(".chat_id").val();

            Swal.fire({
                title: "{{ __('locale.labels.are_you_sure') }}",
                text: "{{ __('locale.labels.able_to_revert') }}",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: "Yes, restore it",
                customClass: {
                    confirmButton: 'btn btn-primary',
                    cancelButton: 'btn btn-outline-danger ms-1'
                },
                buttonsStyling: false,
            }).then(function (result) {
                if (result.value) {
                    $.ajax({
                        url: "{{ url('/chat-box')}}" + '/' + sms_id + '/restore',
                        type: "POST",
                        data: {
                            _token: "{{csrf_token()}}"
                        },
                        success: function (response) {

                            if (response.status === 'success') {
                                toastr['success'](response.message, '{{__('locale.labels.success')}}!!', {
                                    closeButton: true,
                                    positionClass: 'toast-top-right',
                                    progressBar: true,
                                    newestOnTop: true,
                                    rtl: isRtl
                                });

                                setTimeout(function () {
                                    window.location.reload(); // then reload the page.(3)
                                }, 3000);

                            } else {
                                toastr['warning'](response.message, '{{ __('locale.labels.warning') }}!', {
                                    closeButton: true,
                                    positionClass: 'toast-top-right',
                                    progressBar: true,
                                    newestOnTop: true,
                                    rtl: isRtl
                                });
                            }
                        },
                        error: function (reject) {
                            if (reject.status === 422) {
                                let errors = reject.responseJSON.errors;
                                $.each(errors, function (key, value) {
                                    toastr['warning'](value[0], "{{__('locale.labels.attention')}}", {
                                        closeButton: true,
                                        positionClass: 'toast-top-right',
                                        progressBar: true,
                                        newestOnTop: true,
                                        rtl: isRtl
                                    });
                                });
                            } else {
                                toastr['warning'](reject.responseJSON.message, "{{__('locale.labels.attention')}}", {
                                    closeButton: true,
                                    positionClass: 'toast-top-right',
                                    progressBar: true,
                                    newestOnTop: true,
                                    rtl: isRtl
                                });
                            }
                        }
                    });
                }
            })

        })
        let selectedChatIds = [];
        $(".b_del_btn").on('click', function (event) {
            event.preventDefault();
            let chat_box_ids = selectedChatIds;

            Swal.fire({
                title: "{{ __('locale.labels.are_you_sure') }}",
                text: "{{ __('locale.labels.able_to_revert') }}",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: "{{ __('locale.labels.delete_it') }}",
                customClass: {
                    confirmButton: 'btn btn-primary',
                    cancelButton: 'btn btn-outline-danger ms-1'
                },
                buttonsStyling: false,
            }).then(function (result) {
                if (result.value) {
                    $.ajax({
                        url: "{{ url('/chat-box')}}" + '/b_delete',
                        type: "POST",
                        data: {
                            _token: "{{csrf_token()}}",
                            chat_box : chat_box_ids
                        },
                        success: function (response) {

                            if (response.status === 'success') {
                                toastr['success'](response.message, '{{__('locale.labels.success')}}!!', {
                                    closeButton: true,
                                    positionClass: 'toast-top-right',
                                    progressBar: true,
                                    newestOnTop: true,
                                    rtl: isRtl
                                });

                                setTimeout(function () {
                                    window.location.reload(); // then reload the page.(3)
                                }, 2000);

                            } else {
                                toastr['warning'](response.message, '{{ __('locale.labels.warning') }}!', {
                                    closeButton: true,
                                    positionClass: 'toast-top-right',
                                    progressBar: true,
                                    newestOnTop: true,
                                    rtl: isRtl
                                });
                            }
                        },
                        error: function (reject) {
                            if (reject.status === 422) {
                                let errors = reject.responseJSON.errors;
                                $.each(errors, function (key, value) {
                                    toastr['warning'](value[0], "{{__('locale.labels.attention')}}", {
                                        closeButton: true,
                                        positionClass: 'toast-top-right',
                                        progressBar: true,
                                        newestOnTop: true,
                                        rtl: isRtl
                                    });
                                });
                            } else {
                                toastr['warning'](reject.responseJSON.message, "{{__('locale.labels.attention')}}", {
                                    closeButton: true,
                                    positionClass: 'toast-top-right',
                                    progressBar: true,
                                    newestOnTop: true,
                                    rtl: isRtl
                                });
                            }
                        }
                    });
                }
            })

        })
        
        $('.s_del_btn').on('click',function(){
        var inputValue = $('.search_chat_new').val().trim();
        if (/^\d+$/.test(inputValue) || inputValue == null || inputValue == "") {
        } else {
            // If it's a text string, send an AJAX request to /search
            var authUserId = {{ Auth::id() }}; // Assuming you're using Blade syntax
            $.ajax({
                url: "/chat-box/search",
                method: "POST", // Adjust the method as needed
                headers: {
                    'X-CSRF-TOKEN': "{{csrf_token()}}"
                },
                data: {
                    input: inputValue,
                    authUserId: authUserId,
                },
                success: function(response) {
                    var ids= response.data;
                    $('.back_fil').each(function() {
                        var ele = $(this);
                        var dataId = $(this).data("box-id");
                        if (ids.includes(dataId)) {
                            $(this).show();
                        } else {
                            $(this).hide();
                        }
                    });
                },
                error: function(error) {
                    console.error("AJAX error:", error);
                }
            });
        }
    });

    
        // Handle checkbox change event
        // Define an array to store selected chat ID
        
        // Handle bulk select checkbox change
        $("#bulk-select-checkbox").on("change", function() {
            // Select or deselect all checkboxes based on the bulk select checkbox state
            $(".chat-checkbox-bulk").prop("checked", $(this).prop("checked"));
        
            // Update the selectedChatIds array accordingly
            if ($(this).prop("checked")) {
                // If bulk select is checked, add all chat IDs to the array
                selectedChatIds = $(".chat-checkbox-bulk").map(function() {
                    return $(this).data("chat-id");
                }).get();
            } else {
                // If bulk select is unchecked, clear the array
                selectedChatIds = [];
            }
        });
        
        // Handle individual checkbox change
        $(".chat-users-list").on("change", ".chat-checkbox-bulk", function() {
            const chatId = $(this).data('chat-id');
        
            if ($(this).prop('checked')) {
                // Add the chat ID to the array if the checkbox is checked
                selectedChatIds.push(chatId);
            } else {
                // Remove the chat ID from the array if the checkbox is unchecked
                selectedChatIds = selectedChatIds.filter(id => id !== chatId);
            }
        
            // Update the bulk select checkbox based on the number of selected checkboxes
            $("#bulk-select-checkbox").prop("checked", $(".chat-checkbox-bulk:checked").length === $(".chat-checkbox-bulk").length);
        
            // Encode the array as JSON and store it in a variable
            const selectedChatIdsJSON = JSON.stringify(selectedChatIds);
        });



        $(".add-to-blacklist").on('click', function (event) {
            event.preventDefault();
            let sms_id = $(".chat_id").val();

            Swal.fire({
                title: "{{ __('locale.labels.are_you_sure') }}",
                text: "{{ __('locale.labels.remove_blacklist') }}",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: "{{ __('locale.labels.block') }}",
                customClass: {
                    confirmButton: 'btn btn-primary',
                    cancelButton: 'btn btn-outline-danger ms-1'
                },
                buttonsStyling: false,
            }).then(function (result) {
                if (result.value) {
                    $.ajax({
                        url: "{{ url('/chat-box')}}" + '/' + sms_id + '/block',
                        type: "POST",
                        data: {
                            _token: "{{csrf_token()}}"
                        },
                        success: function (response) {

                            if (response.status === 'success') {
                                toastr['success'](response.message, '{{__('locale.labels.success')}}!!', {
                                    closeButton: true,
                                    positionClass: 'toast-top-right',
                                    progressBar: true,
                                    newestOnTop: true,
                                    rtl: isRtl
                                });

                                setTimeout(function () {
                                    window.location.reload(); // then reload the page.(3)
                                }, 3000);

                            } else {
                                toastr['warning'](response.message, '{{ __('locale.labels.warning') }}!', {
                                    closeButton: true,
                                    positionClass: 'toast-top-right',
                                    progressBar: true,
                                    newestOnTop: true,
                                    rtl: isRtl
                                });
                            }
                        },
                        error: function (reject) {
                            if (reject.status === 422) {
                                let errors = reject.responseJSON.errors;
                                $.each(errors, function (key, value) {
                                    toastr['warning'](value[0], "{{__('locale.labels.attention')}}", {
                                        closeButton: true,
                                        positionClass: 'toast-top-right',
                                        progressBar: true,
                                        newestOnTop: true,
                                        rtl: isRtl
                                    });
                                });
                            } else {
                                toastr['warning'](reject.responseJSON.message, "{{__('locale.labels.attention')}}", {
                                    closeButton: true,
                                    positionClass: 'toast-top-right',
                                    progressBar: true,
                                    newestOnTop: true,
                                    rtl: isRtl
                                });
                            }
                        }
                    });
                }
            })

        })

        @if(config('broadcasting.connections.pusher.app_id'))
        let activeChatID = $('.chat-users-list li.active').attr('data-id');

        window.Echo = new Echo({
            broadcaster: 'pusher',
            key: "{{ config('broadcasting.connections.pusher.key') }}",
            cluster: "{{ config('broadcasting.connections.pusher.options.cluster') }}",
            encrypted: true,
            authEndpoint: '{{config('app.url')}}/broadcasting/auth'
        });

        Pusher.logToConsole = false;

        Echo.private('chat').listen('MessageReceived', (e) => {
            // chatHistory.empty();
            chatContainer.animate({scrollTop: chatContainer[0].scrollHeight}, 0);

            let chat_id = e.data.uid;
            let box_id = e.data.id;

            $.ajax({
                url: `{{ url('/chat-box')}}/${chat_id}/notification`,
                type: "POST",
                data: {
                    _token: "{{csrf_token()}}"
                },
                success: function (response) {
                    activeChatID = $('.chat-users-list li.active').attr('data-id');
                    let details = `<input type="hidden" value="${chat_id}" name="chat_id" class="chat_id">`;
                    const $contact = $(`.media-list li[data-box-id=${box_id}]`);
                    const $counter = $(".counter", $contact).removeAttr('hidden');
                    $(".notification_count", $contact).html(e.data.notification);

                    const sms = JSON.parse(response.data);
                    let media_url = '';
                    let message = '';

                    if (sms.media_url !== null) {
                        let fileType = isImageOrVideo(sms.media_url);
                        if (fileType === 'video') {
                            media_url = `<p><video src="${sms.media_url}" controls>Your browser does not support the video tag. <video/></p>`;
                        } else if (fileType === 'audio') {
                            media_url = `<p><audio src="${sms.media_url}" controls>Your browser does not support the audio element. </audio></p>`;
                        } else {
                            media_url = `<p><img src="${sms.media_url}" alt=""/></p>`;
                        }
                    }

                    if (sms.message !== null) {
                        message = `<p>${sms.message}</p>`;
                    }

                    if (sms.send_by === 'to') {
                        details += `<div class="chat chat-left">
                        <div class="chat-avatar">
                          <a class="avatar m-0" href="#">
                            <img src="{{asset('images/profile/profile.jpg')}}" alt="avatar" height="40" width="40"/>
                          </a>
                        </div>
                        <div class="chat-body">
                          <div class="chat-content">
                            ${media_url}
                            ${message}
                          </div>
                        </div>
                      </div>`;
                    } else {
                        details += `<div class="chat">
                        <div class="chat-avatar">
                          <a class="avatar m-0" href="#">
                            <img src="{{  route('user.avatar', Auth::user()->uid) }}" alt="avatar" height="40" width="40"/>
                          </a>
                        </div>
                        <div class="chat-body">
                          <div class="chat-content">
                          ${media_url}
                          ${message}
                          </div>
                          </div>
                          </div>`;
                    }

                    if (chat_id === activeChatID) {
                        chatHistory.append(details);
                        chatContainer.animate({scrollTop: chatContainer[0].scrollHeight}, 0);
                    } else {
                        $counter.html(e.data.notification);
                        $counter.removeAttr('hidden');
                    }
                }
            });
        });
        @endif
    </script>
@endsection

