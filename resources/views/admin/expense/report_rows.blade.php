                            @foreach($data as $key => $report)
                                <tr>
                                    <td>{{ $data->firstItem() + $key }}&nbsp;
                                        @if($report->pdf_status == 0)
                                        <input type="checkbox" class="rowCheckbox" aria-label="Select trip {{ $report->id }}" name="trip_ids[]" value="{{ $report->id }}" data-start-km="{{ $report->starting_km }}" data-end-km="{{ $report->end_km }}">
                                        @endif
                                    </td>
                                    <td>{{ $report->user->name ?? "-" }}</td>
                                    <td>{{ \Carbon\Carbon::parse($report->trip_date)->format('d M Y') }}</td>
                                    <td>{{ $report->tourType->name ?? "-" }}</td>
                                    <td>{{ $report->start_time ?? "-" }}</td>
                                    <td>{{ $report->end_time ?? "-" }}</td>
                                    <td>{{ $report->place_to_visit ?? "-" }}</td>
                                    <td>{{ $report->travelMode->name ?? "-" }}</td>
                                    <td>{{ $report->starting_km ?? "-" }}</td>
                                    <td>{{ $report->end_km ?? "-" }}</td>
                                    <td>{{ $report->end_km - $report->starting_km }}</td>
                                    <td>{{ $report->total_distance_km ?? "-" }}</td>
                                    {{-- <td>{{ $report->end_km - $report->starting_km }}</td> --}}
                                    <td>{{ (($report->end_km ?? 0) - ($report->starting_km ?? 0)) - ($report->total_distance_km ?? 0) }}</td>
                                    <td>{{ number_format($report->ta_exp ?? 0, 2) }}</td>
                                    <td>{{ number_format($report->da_exp ?? 0, 2) }}</td>
                                    <td>{{ number_format($report->other_exp ?? 0, 2) }}</td>
                                    <td>{{ number_format($report->total_exp ?? 0, 2) }}</td>

                               
                                </tr>
                            @endforeach
