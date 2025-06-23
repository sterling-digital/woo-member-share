/**
 * Frontend JavaScript for Woo Member Share
 * 
 * @package WooMemberShare
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        initGroupManagement();
        initFormHandling();
    });
    
    /**
     * Initialize group management functionality
     */
    function initGroupManagement() {
        // Toggle group details
        $('.wms-toggle-group').on('click', function() {
            var target = $(this).data('target');
            var details = $('#' + target);
            var button = $(this);
            
            if (details.is(':visible')) {
                details.slideUp(300, function() {
                    button.text(wms_frontend.labels.manage || 'Manage');
                });
            } else {
                details.slideDown(300, function() {
                    button.text(wms_frontend.labels.hide || 'Hide');
                });
            }
        });
        
        // Auto-expand first group if only one exists
        var groups = $('.wms-group-container');
        if (groups.length === 1) {
            groups.find('.wms-toggle-group').trigger('click');
        }
    }
    
    /**
     * Initialize form handling
     */
    function initFormHandling() {
        // Add loading states to forms
        $('.wms-action-form, .wms-rename-form, .wms-invite-form, .wms-remove-member-form').on('submit', function() {
            var form = $(this);
            var button = form.find('button[type="submit"]');
            var originalText = button.text();
            
            // Add loading state
            button.prop('disabled', true);
            button.text(wms_frontend.labels.loading || 'Loading...');
            form.addClass('wms-loading');
            
            // Re-enable after a delay (in case submission fails)
            setTimeout(function() {
                button.prop('disabled', false);
                button.text(originalText);
                form.removeClass('wms-loading');
            }, 5000);
        });
        
        // Validate email input
        $('input[type="email"]').on('blur', function() {
            var email = $(this).val();
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                $(this).addClass('error');
                showNotice('Please enter a valid email address.', 'error');
            } else {
                $(this).removeClass('error');
            }
        });
        
        // Prevent duplicate invitations
        $('.wms-invite-form').on('submit', function(e) {
            var form = $(this);
            var email = form.find('input[type="email"]').val().toLowerCase();
            var groupContainer = form.closest('.wms-group-container');
            var existingEmails = [];
            
            // Collect existing member emails
            groupContainer.find('.wms-members-table tbody tr').each(function() {
                var memberEmail = $(this).find('td:first').text().toLowerCase().trim();
                if (memberEmail) {
                    existingEmails.push(memberEmail);
                }
            });
            
            // Check for duplicates
            if (existingEmails.indexOf(email) !== -1) {
                e.preventDefault();
                showNotice('This email is already a member of this group.', 'error');
                return false;
            }
        });
        
        // Confirm dangerous actions
        $('.wms-remove-member-form button, .wms-action-form button[onclick*="confirm"]').on('click', function(e) {
            var button = $(this);
            var action = button.closest('form').find('input[name="wms_action"]').val();
            var confirmMessage = '';
            
            switch (action) {
                case 'remove_member':
                    confirmMessage = 'Are you sure you want to remove this member? They will lose access to membership benefits.';
                    break;
                case 'leave_group':
                    confirmMessage = 'Are you sure you want to leave this group? You will lose access to membership benefits.';
                    break;
            }
            
            if (confirmMessage && !confirm(confirmMessage)) {
                e.preventDefault();
                return false;
            }
        });
    }
    
    /**
     * Show notification message
     */
    function showNotice(message, type) {
        type = type || 'info';
        
        var notice = $('<div class="wms-notice wms-notice-' + type + '">' + message + '</div>');
        
        // Remove existing notices
        $('.wms-notice').remove();
        
        // Add new notice
        $('.woo-member-share-groups').prepend(notice);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            notice.fadeOut(300, function() {
                notice.remove();
            });
        }, 5000);
        
        // Allow manual dismissal
        notice.on('click', function() {
            $(this).fadeOut(300, function() {
                $(this).remove();
            });
        });
    }
    
    /**
     * Copy text to clipboard
     */
    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            // Modern browsers
            navigator.clipboard.writeText(text).then(function() {
                showNotice('Copied to clipboard!', 'success');
            }).catch(function() {
                fallbackCopyToClipboard(text);
            });
        } else {
            fallbackCopyToClipboard(text);
        }
    }
    
    /**
     * Fallback copy to clipboard for older browsers
     */
    function fallbackCopyToClipboard(text) {
        var textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            showNotice('Copied to clipboard!', 'success');
        } catch (err) {
            showNotice('Failed to copy to clipboard. Please copy manually.', 'error');
        }
        
        document.body.removeChild(textArea);
    }
    
    /**
     * Initialize tooltips (if needed in future)
     */
    function initTooltips() {
        $('[data-tooltip]').each(function() {
            var element = $(this);
            var tooltip = $('<div class="wms-tooltip">' + element.data('tooltip') + '</div>');
            
            element.on('mouseenter', function() {
                $('body').append(tooltip);
                tooltip.css({
                    position: 'absolute',
                    top: element.offset().top - tooltip.outerHeight() - 5,
                    left: element.offset().left + (element.outerWidth() / 2) - (tooltip.outerWidth() / 2),
                    zIndex: 9999
                }).fadeIn(200);
            });
            
            element.on('mouseleave', function() {
                tooltip.fadeOut(200, function() {
                    tooltip.remove();
                });
            });
        });
    }
    
    // Make functions available globally if needed
    window.wmsGroupManagement = {
        showNotice: showNotice,
        copyToClipboard: copyToClipboard
    };
    
})(jQuery);
