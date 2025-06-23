/**
 * Admin JavaScript for Woo Member Share
 * 
 * @package WooMemberShare
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Product data panel functionality
        initProductDataPanel();
        
        // Variation settings functionality
        initVariationSettings();
        
    });
    
    /**
     * Initialize product data panel functionality
     */
    function initProductDataPanel() {
        var $enableSharing = $('#_enable_membership_sharing');
        var $limitType = $('input[name="_subaccount_limit_type"]');
        var $limitField = $('#_subaccount_limit').closest('.form-field');
        
        // Toggle limit field based on allocation type
        function toggleLimitField() {
            if ($limitType.filter(':checked').val() === 'fixed') {
                $limitField.show();
            } else {
                $limitField.hide();
            }
        }
        
        // Initial state
        toggleLimitField();
        
        // Event listeners
        $limitType.on('change', toggleLimitField);
        
        // Show/hide entire panel based on enable sharing checkbox
        function toggleSharingOptions() {
            var $options = $('#member_share_product_data .options_group').children().not(':first');
            if ($enableSharing.is(':checked')) {
                $options.show();
            } else {
                $options.hide();
            }
        }
        
        toggleSharingOptions();
        $enableSharing.on('change', toggleSharingOptions);
    }
    
    /**
     * Initialize variation settings functionality
     */
    function initVariationSettings() {
        $(document).on('change', '[id^="_variation_subaccount_limit_type_"]', function() {
            var $select = $(this);
            var variationId = $select.attr('id').replace('_variation_subaccount_limit_type_', '');
            var $limitField = $('#_variation_subaccount_limit\\[' + variationId + '\\]').closest('.form-field');
            
            if ($select.val() === 'fixed') {
                $limitField.show();
            } else {
                $limitField.hide();
            }
        });
        
        $(document).on('change', '[name^="_variation_enable_sharing"]', function() {
            var $checkbox = $(this);
            var variationId = $checkbox.attr('name').match(/\[(\d+)\]/)[1];
            var $settings = $checkbox.closest('.woo-member-share-variation-settings');
            var $options = $settings.find('.form-field').not($checkbox.closest('.form-field'));
            
            if ($checkbox.is(':checked')) {
                $options.show();
            } else {
                $options.hide();
            }
        });
        
        // Initialize variation settings on page load
        $('[name^="_variation_enable_sharing"]').each(function() {
            $(this).trigger('change');
        });
        
        $('[id^="_variation_subaccount_limit_type_"]').each(function() {
            $(this).trigger('change');
        });
    }
    
})(jQuery);
