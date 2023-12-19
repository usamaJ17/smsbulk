<div class="row">
    <div class="col-12">
        <div class="card">
            <table class="table mb-0">
                <thead class="thead-primary">
                <tr>
                    <th scope="col">{{ __('locale.labels.submitted') }}</th>
                    <th scope="col">{{ __('locale.labels.status') }}</th>
                    <th scope="col">{{ __('locale.labels.message') }}</th>
                </tr>
                </thead>
                <tbody>


                @if ($currentJob)
                    @php
                        $progress = $contact->getProgress($currentJob);
                    @endphp
                    @if ($progress['status'] == 'failed')
                        <tr>
                            <td class="text-center" colspan="5">
                                {{ __('locale.exceptions.something_went_wrong') }}
                            </td>
                        </tr>
                    @else
                        <tr>
                            <td> {{ \App\Library\Tool::customerDateTime($currentJob->created_at) }} </td>
                            <td> {!! $currentJob->getStatus() !!} </td>
                            <td>{{ strtoupper($progress['message']) }}</td>
                        </tr>

                    @endif

                @else
                    <tr>
                        <td class="text-center" colspan="5">
                            {{ __('locale.datatables.no_results') }}
                        </td>
                    </tr>
                @endif

                </tbody>
            </table>
        </div>
    </div>
</div>
