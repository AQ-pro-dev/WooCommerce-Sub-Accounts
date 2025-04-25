jQuery(document).ready(function ($) {
    // Toggle subaccount list on selection change
    $('#place_order').hide();

    // Check if the dummy button already exists to avoid duplication
    if ($('#place_order_dummy').length === 0) {
        // Create a new button and insert it after the original one
        $('<button>', {
            id: 'place_order_dummy',
            class: 'button alt',
            type: 'button', // Ensures it doesn't submit the form directly
            text: 'Place order'
        }).insertAfter('#place_order');
    }
    $('#subaccount_type').on('change', function () {
        const selectedType = $(this).val();
        $('.subaccount-sublist').hide();
        if (selectedType !== 'self') {
            $('.sublist-' + selectedType).show();
            $('.add-new-subaccount-btn[data-type="' + selectedType + '"]').show();
        } else {
            $('.add-new-subaccount-btn').hide();
        }
    });
    

    // Show modal on add new button click
    $('.add-new-subaccount-btn').on('click', function (e) {
        e.preventDefault();
        const type = $(this).data('type');
        $('#modal_subaccount_type').val(type);
        $('#modal_subaccount_name').val('');
        $('#subaccountModal').fadeIn();
    });

    // Close modal on close button click
    $('#subaccountModal .close').on('click', function () {
        $('#subaccountModal').fadeOut();
    });

    // Close modal when clicking outside modal content (only if click is not inside input or form)
    $(document).on('mousedown', function (e) {
        const modalContent = $('#subaccountModal .modal-content');
        const isClickInsideModal = modalContent.is(e.target) || modalContent.has(e.target).length > 0;
        if (!isClickInsideModal && $('#subaccountModal').is(':visible')) {
            $('#subaccountModal').fadeOut();
        }
    });

    // Save subaccount from modal
    $('#save_subaccount_btn').on('click', function () {
        const type = $('#modal_subaccount_type').val();
        const name = $('#modal_subaccount_name').val().trim();

        if (name === '') {
            alert('Please enter a name for the sub account.');
            return;
        }

        const radioId = 'subaccount_' + type + '_' + name.replace(/\s+/g, '_');
		const newRadio = ' <label><input type="radio" id="' + radioId + '" name="selected_subaccount" value="' + name + '" checked /> ' + name + '</label><br>';
        const container = $('.sublist-' + type);
		
		// Check if any new radio already exists, if not, add heading
if (container.find('.new-subaccount-heading').length === 0) {
    container.prepend('<h3 class="new-subaccount-heading" style="margin-bottom: 10px; text-transform: capitalize;">Recently Added My ' + type  + '  Account :</h3>');
}
     container.find('.new-subaccount-heading').after(newRadio);

        const hiddenField = '<input type="hidden" name="subaccount_name_' + type + '[]" value="' + name + '" />';
        container.append(hiddenField);

        $('#subaccountModal').fadeOut();
    });

    // Trigger default view
    $('#subaccount_type').trigger('change'); 
    
    let qrLock = false;
    let qrcodeGenerated = false;
    let awaitQR = false; // new flag to catch 2nd submission

    $(document).on('click', '#place_order_custom', function (e) {
        if (qrLock) {
            // Already processing QR, prevent duplicate submission
            e.preventDefault();
            return false;
        }

        if (qrcodeGenerated && awaitQR) {
            // Let WooCommerce proceed after QR generation is complete
            return true;
        }

        const subaccountType = $('#subaccount_type').val();
		const selectedRadio = $('input[name="selected_subaccount"]:checked').val();
        const modalSubaccountName = $('#modal_subaccount_name').val().trim();
        const uid = $('#Uid').val(); // logged-in user ID

        const subaccountIdentifier = (subaccountType === 'self') ? uid : (selectedRadio || modalSubaccountName);
		// alert('subaccountType'+subaccountType+'selectedRadio'+selectedRadio+'modalSubaccountName'+modalSubaccountName+'uid'+uid);
		// exit;
        if (!subaccountIdentifier) {
            alert("Please select or create a subaccount before placing order.");
            e.preventDefault();
            return false;
        }

        e.preventDefault(); // Always block Woo until QR is done
        qrLock = true;

        const isExisting = /^\d+$/.test(subaccountIdentifier); // numeric = user ID

        const proceedWithQR = (finalIdentifier) => {
            generateQrcode(finalIdentifier, subaccountType).then(() => {
                qrcodeGenerated = true;
                qrLock = false;
                awaitQR = true;
                $('form.checkout').trigger('submit'); // safely retry Woo checkout
            }).catch((err) => {
                console.error("QR code generation failed:", err);
                qrLock = false;
                alert("QR code generation failed. Please try again.");
            });
        };

        if (subaccountType !== 'self' && !isExisting) {
            // New subaccount creation via AJAX
            $.ajax({
				url: ajax_object_for_Qrcode.ajax_url,
				method: 'POST',
				dataType: 'json',
				data: {
					action: 'create_subaccount_from_checkout',
					subaccount_type: subaccountType,
					modal_subaccount_name: selectedRadio
				},
				success: function (response) {
					console.log(response);
					if (response.success && response.data.user_id) {
						proceedWithQR(response.data.user_id);
					} else {
						const message = response.message || 'Failed to create subaccount.';
						alert(message);
						console.error(response);
						qrLock = false;
					}
				},
				error: function (xhr) {
					let message = 'Error creating subaccount.';
					if (xhr.responseJSON && xhr.responseJSON.message) {
						message = xhr.responseJSON.message;
					}
					alert(message);
					console.error(xhr);
					qrLock = false;
				}
			});

        } else {
            // Existing subaccount or self
            proceedWithQR(subaccountIdentifier);
        }

        return false; // stop WooCommerce from continuing until retry
    });


    

    function generateQrcode(identifier, type) {
        return new Promise((resolve, reject) => {
            const $qrcode = $('#qrcode');
            if (!$qrcode.length) {
                reject('QR code container not found');
                return;
            }
    
            $qrcode.empty();
    
            const productID = $('#product_id').val() || 0;
            const baseUrl = $('#qr_code_scanning_link').val();
            const encodedData = btoa(identifier + "," + productID);
            const finalUrl = baseUrl.replace("patient-details", "pd") + encodedData;
            const withoutHttpPrefix = removeHttpPrefix(finalUrl);
    
            try {
                const qr = qrcode(3, 'L');
                qr.addData(withoutHttpPrefix);
                qr.make();
    
                let svg = qr.createSvgTag({ scalable: true, margin: 0 });
                svg = svg.replace(/<rect[^>]*fill="[^"]*"/g, '').replace(/<path/g, `<path fill="#373435"`);
    
                $qrcode.html(svg);
    
                const serializer = new XMLSerializer();
                const svgString = serializer.serializeToString($qrcode[0]);
    
                $.ajax({
                    url: ajax_object_for_Qrcode.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'savesub_qr_code',
                        identifier: identifier,
                        type: type, // 👈 include self/other info
                        product_id: productID,
                        qr_code_image: svgString,
                        qr_code_data: withoutHttpPrefix
                    },
                    success: function (response) {
                        if (response.success && response.data.url) {
                            $('#qr_code_url').val(response.data.url);
                            resolve();
                        } else {
                            reject('QR code save failed');
                        }
                    },
                    error: function (xhr) {
                        reject('AJAX error: ' + xhr.statusText);
                    }
                });
            } catch (err) {
                reject('QR generation failed: ' + err.message);
            }
        });
    }
    
    
    //remove http prefix 
    function removeHttpPrefix(url) {
        return url.replace(/^https?:\/\//, ''); // Removes https:// or https://
    }   
});


