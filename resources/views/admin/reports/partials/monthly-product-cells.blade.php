@foreach($row['monthly'] as $cell)
<td>{{ \App\Services\MonthlyProductReport::formatNumber($cell['qty'], 3) }}</td><td>{{ \App\Services\MonthlyProductReport::formatNumber($cell['value'], 2) }}</td>
@endforeach
<td class="fw-bold total-cell">{{ \App\Services\MonthlyProductReport::formatNumber($row['qty'], 3) }}</td><td class="fw-bold total-cell">{{ \App\Services\MonthlyProductReport::formatNumber($row['value'], 2) }}</td>
