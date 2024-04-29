<?php ?>
<div class="woocommerce-layout" style="padding: 10px 20px;background-color: white;margin-left: -18px;">
	<div class="woocommerce-layout__header">
		<div class="woocommerce-layout__header-wrapper">
			<h1 data-wp-c16t="true" data-wp-component="Text" class="components-truncate components-text woocommerce-layout__header-heading css-wv5nn e19lxcc00">Captured Transcations</h1>
		</div>
    </div>
</div>	
<div style="padding-top: 30px; padding-right:10px">
<table class='wp-list-table widefat fixed striped table-view-list' id='cap_rec'>
    <thead>
	    <tr>
            <th>Sr.No</th>
            <th>Order Id</th>
            <th>Captured Amount</th>
            <th>Captured Date</th>
            <th>Refunded Amount</th>
            <th>Refund Amount</th>
            <th>Action</th>
        </tr>
	</thead>
	<tbody>
	<?php 
	    if($results){ 
	        foreach ( $results as $row ) {
	?>
	            <tr class='crow'>
				    <td><?php echo $i; ?></td>
					<td><?php echo $row->order_id; ?></td>
					<td><?php echo $row->capture_amt; ?></td>
					<td><?php echo $row->capture_date; ?></td>
					<td><?php echo $row->refunded_amt; ?></td>
					<td><input type='text' class='ref_emt'><span style='color:red'></span></td>
					<td>
					    <input type='button' id='<?php echo $row->tran_ref; ?>' value='Refund' class='refund_btn'>
					    <label style='font-size: medium;color: #127c2c;display:none' class='process'>Processing......</label>
					</td>
                </tr>	
	<?php 	    $i++;
	        } 
        }
	?>
	</tbody>
</table>
</div>
<script>
	jQuery(document).ready(function($) {
		$('#cap_rec').DataTable();
	});
  jQuery(document).on('click', '.refund_btn', function() {
	var $this = jQuery(this); 			
	var tranRef = $this.attr('id');
	var row = $this.closest('tr'); 
	var orderId = row.find('td:eq(1)').text().trim(); 
	var capturedAmount = row.find('td:eq(2)').text().trim();
	var capturedDate = row.find('td:eq(3)').text(); 
	var refundedAmt = row.find('td:eq(4)').text().trim() == '' ? 0 : row.find('td:eq(4)').text().trim();
	var refundAmt = row.find('input[type="text"]').val().trim();
		
	if (refundAmt !== '' && /^\d*\.?\d{0,2}$/.test(refundAmt)) {
		if (parseFloat(refundAmt) <= (parseFloat(capturedAmount) - parseFloat(refundedAmt))) {
			$this.hide();
			row.find('.process').show();
			jQuery.ajax({
				url: '<?php echo admin_url('admin-ajax.php'); ?>',
				type: 'POST',
				data: {
					action: 'captured_refund',
					order_id: orderId,
					captured_amount: capturedAmount,
					refunded_amount: refundedAmt,
					refund_amount: refundAmt,
					tran_ref:tranRef
				},
				success: function(response) {								
					window.location.reload(true);
				},
				error: function(xhr, textStatus, errorThrown) {
					window.location.reload(true);
				}
			});
		} else {
			row.find('span').text('Input value is greater than the maximum allowed value');						
		}
	} else {
		row.find('span').text('Input value is blank or contains non-numeric characters or more than 2 decimal.');					
	}
	setTimeout(function() {
		row.find('span').text('');
	}, 3000);
				
  });				
</script>