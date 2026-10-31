@foreach($row['monthly'] as $cell)
<td>{{ number_format($cell['qty'], 3) }}</td><td>{{ number_format($cell['value'], 2) }}</td>
@endforeach
<td class="fw-bold">{{ number_format($row['qty'], 3) }}</td><td class="fw-bold">{{ number_format($row['value'], 2) }}</td>
