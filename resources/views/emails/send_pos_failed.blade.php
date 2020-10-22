<table style="border:1px solid #C0C0C0;border-collapse:collapse;padding:5px; width: 100%">
	<thead>
		<tr>
			<th style="border:1px solid #C0C0C0;padding:5px;background:#F0F0F0;">Transaction Date</th>
			<th style="border:1px solid #C0C0C0;padding:5px;background:#F0F0F0;">Receipt Number</th>
			<th style="border:1px solid #C0C0C0;padding:5px;background:#F0F0F0;">Customer Name</th>
			<th style="border:1px solid #C0C0C0;padding:5px;background:#F0F0F0;">Customer Phone</th>
		</tr>
	</thead>
	<tbody>
		@foreach($trxs as $trx)
		<tr>
			<td style="border:1px solid #C0C0C0;padding:5px;">
				{{ date('d F Y H:i', strtotime($trx['transaction_date'])) }}
			</td>
			<td style="border:1px solid #C0C0C0;padding:5px;">
				{{ $trx['transaction_receipt_number'] }}
			</td>
			<td style="border:1px solid #C0C0C0;padding:5px;">
				{{ $trx['name'] }}
			</td>
			<td style="border:1px solid #C0C0C0;padding:5px;">
				{{ $trx['phone'] }}
			</td>
		</tr>
		@endforeach
	</tbody>
</table>