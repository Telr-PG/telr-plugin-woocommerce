<?php ?>
<button type="button" class="button" id="capture_button">Capture</button>
<button type="button" class="button" id="release_button">Release</button>	
<div class="wc-order-data-row wc-order-data-row-toggle wc-telr-partial-cap-rel" data-ol-has-click-handler style="display: none;">
	<table class="wc-order-totals">
		<tr>
			<td class="label">Authorization total:</td>
			<td class="total amount" id="telr-auth-total"></td>
		</tr>
		<tr>
			<td class="label">Amount already captured:</td>
			<td class="total amount" id="telr-already-captured"></td>
		</tr>
		<tr>
			<td class="label">Amount already released:</td>
			<td class="total amount" id="telr-already-release"></td>
		</tr>
		<tr>
			<td class="label">Amount authorized remaining:</td>
			<td class="total amount" id="telr-auth-remaining" value="<?php esc_attr_e($auth_remaining); ?>"></td>
		</tr>
		<tr>
			<td class="label"><label for="telr-cap-rel-amount" id="actionLableAmt"></label></td>
			<td class="total">
				<input type="text" class="text" id="telr-cap-rel-amount" name="telr-cap-rel-amount" class="wc_input_price" />
				<div class="clear"></div>
			</td>
		</tr>				
	</table>
	<div class="clear"></div>
	<div class="cap-rel-actions" style="margin-top: 8px;padding-top: 12px;border-top: 1px solid #dfdfdf;">
		<button type="button" class="button button-primary cap-rel-action" disabled="disabled" style="float: right;">
			<span id="action_lable"></span> 
			<span class="cap-rel-amount">
				<span class="woocommerce-Price-amount amount">
					<bdi></bdi>
				</span>
			</span> via Telr
		</button>
		<button type="button" class="button cancel-action" style="float: left;">Cancel</button>

		<div class="clear"></div>
	</div>
	<input type="hidden" id="action_type">
</div>

<script>
	jQuery(document).ready(function($) {			
		$('div.wc-telr-partial-cap-rel' ).appendTo("#woocommerce-order-items .inside");				
		$("#telr-auth-total").text(formatCurrency(<?php esc_attr_e($order->get_total()); ?>));
		$("#telr-already-captured").text(formatCurrency(<?php esc_attr_e($already_captured); ?>));
		$("#telr-already-release").text(formatCurrency(<?php esc_attr_e($already_releaseed); ?>));
		$("#telr-auth-remaining").text(formatCurrency(<?php esc_attr_e($auth_remaining); ?>));
		$('button.cap-rel-action .woocommerce-Price-amount.amount').text(formatCurrency(0));
		
		$('#capture_button').on('click', function() {
			$('#actionLableAmt').text('Capture amount:');
			$('#action_lable').text('Capture');
			$('#action_type').val('capture');
			$('div.wc-telr-partial-cap-rel').slideDown();
			$('div.wc-order-totals-items').slideUp();
			$('div.wc-order-data-row-toggle').not( 'div.wc-telr-partial-cap-rel' ).slideUp();					
		});
		
		$('#release_button').on('click', function() {
			$('#actionLableAmt').text('Release amount:');
			$('#action_lable').text('Release');
			$('#action_type').val('release');
			$('div.wc-telr-partial-cap-rel' ).slideDown();
			$('div.wc-order-totals-items').slideUp();
			$('div.wc-order-data-row-toggle' ).not( 'div.wc-telr-partial-cap-rel' ).slideUp();					
		});
		
		$('.cap-rel-action').on('click', function() {
			var order_id = <?php echo $order->get_id(); ?>;
			var cap_rel_amt = $('#telr-cap-rel-amount').val();
			var action_type = $('#action_type').val();
			var action_url = action_type == 'capture' ? 'capture_payment' : 'release_payment';
			
			$.ajax({
				url: '<?php echo admin_url('admin-ajax.php'); ?>',
				type: 'POST',
				data: {
					action: action_url,
					order_id: order_id,
					amount: cap_rel_amt,
					type:action_type
				},
				success: function(response) {								
					window.location.reload(true);
				},
				error: function(xhr, textStatus, errorThrown) {
					window.location.reload(true);
				}
			});					
		});				
		
		$('.wc-telr-partial-cap-rel #telr-cap-rel-amount').on('input keyup', function() {
			var total = accounting.unformat( $( this ).val(), woocommerce_admin.mon_decimal_point );	
			if ( typeof total !== 'number' || ! ( total > 0 && total <= $("#telr-auth-remaining").attr('value')) ) {
				total = 0;
				$( 'button.cap-rel-action' ).attr( "disabled", true );
			} else {
				$( 'button.cap-rel-action' ).attr( "disabled", false );
			}
			$('#telr-cap-rel-amount').val(total);
			$('button.cap-rel-action .woocommerce-Price-amount.amount').text(formatCurrency(total));
			
		});				
		
		function formatCurrency( val ) {
			return accounting.formatMoney(
				val,
				{
					symbol:    woocommerce_admin_meta_boxes.currency_format_symbol,
					decimal:   woocommerce_admin_meta_boxes.currency_format_decimal_sep,
					thousand:  woocommerce_admin_meta_boxes.currency_format_thousand_sep,
					precision: woocommerce_admin_meta_boxes.currency_format_num_decimals,
					format:    woocommerce_admin_meta_boxes.currency_format
				}
			);
		}
	});
</script>