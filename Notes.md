
# Woo Member Share Plugin Technical Specification

## Overview
We're developing a WooCommerce extension called "Woo Share Membership," designed specifically to extend WooCommerce Memberships functionality. This plugin enables customers to share their purchased membership access with additional users through a group-based system.

## Core Requirements
- Robust yet intuitive plugin that integrates seamlessly with WooCommerce Memberships
- Customizable options allowing customers to define groups and manage member sharing dynamically
- Clear and user-friendly interfaces on both frontend and backend
- Works with variable membership products and both subscription-based and one-time purchases
- Real-time membership benefit activation/deactivation based on main membership status

## Definitions
-  **Customer**: The user who purchases a WooCommerce Membership product
-  **Admin(s)**: WordPress site administrators
-  **Subaccount(s)**: The user(s) that the Customer has invited to share the benefits of their Membership
-  **Group**: The collection of Subaccounts the Customer has authority to manage
-  **Account Billing Manager**: How Subaccounts see the Customer in their interface

## Dependency Management
-  **Critical**: Plugin must check for WooCommerce Memberships dependency on activation
- If WooCommerce Memberships is deactivated, this plugin must automatically deactivate
- Display admin notice explaining the dependency requirement

## Membership Status Logic
-  **Immediate Benefit Management**: Subaccounts' benefits exactly match the main membership status with no grace periods
-  **Persistent Group Data**: Group associations remain intact through renewals, billing lapses, and expirations
-  **Status Behavior**:
- Active membership = Subaccounts get benefits
- Expired/Paused/Canceled membership = Subaccounts lose benefits immediately
- Renewed membership = Subaccounts automatically regain benefits
- Group membership data persists regardless of membership status changes

## Admin Backend Features

### Product-Level Settings
Add new settings in WooCommerce product data panels:

**For Simple Products:**
- **Enable Sharing**: Checkbox to enable membership sharing for this product
- **Subaccount Allocation**: Radio button options (Fixed/Quantity-Based)
- **Subaccount Limit**: Number field (if Fixed selected)

**For Variable Products:**
- **Per-Variation Settings**: Each variation has independent sharing configuration
- **No Inheritance**: Simple enable/disable per variation with individual limits
- **Clear Interface**: Direct settings without complex override capabilities

**Allocation Type Behavior:**
- **Fixed**: No quantity selector on frontend, customer gets predetermined number of subaccounts
- **Quantity-Based**: WooCommerce quantity selector appears, subaccount slots = quantity purchased

### Plugin Options Page
Global settings for customization:
- Custom labels for Groups (singular/plural)
- Custom labels for Subaccounts (singular/plural)
- Email template customization options

### Group Management Page
Admin interface for managing existing groups:
- View all groups across the site
- Manually add/remove Subaccounts for any Group
- Change Group names
- View group status and membership details

## Variable Product Implementation

### Simplified Group Management Strategy
**One Group Per Variation Approach:**
- Each purchased variation creates its own independent group
- No complex limit calculations or inheritance rules
- Clear customer understanding of what each membership provides
- Direct one-to-one relationship between variation and group

### Admin Interface Simplification
**Per-Variation Settings:**
Each variation has simple, independent settings:
- Enable Sharing: Checkbox (yes/no)
- Subaccount Allocation: Radio buttons (Fixed/Quantity-Based)  
- Subaccount Limit: Number field (if Fixed selected)

**Example Variation Setup:**
```
Variation: Basic-1yr ($99)
☑ Enable membership sharing
● Fixed limit: [3] subaccounts
○ Quantity-based allocation

Variation: Pro-1yr ($199) 
☑ Enable membership sharing
○ Fixed limit: [___] subaccounts
● Quantity-based allocation

Variation: Basic-2yr ($179)
☐ Enable membership sharing (disabled)
```

### Customer Experience Benefits
**Multiple Variation Ownership:**
- Customer owns Basic-1yr (3 subaccounts) + Pro-1yr (10 subaccounts)
- Display: Two separate groups: "Basic Annual Group (3 slots)" and "Pro Annual Group (10 slots)"
- Clear Understanding: Customer knows exactly which membership provides which benefits
- Independent Management: Each group operates independently with its own members and status
- Status Dependency: Each group's status directly mirrors its corresponding variation's membership status

## Customer Frontend Features

### My Account Dashboard Integration
- Add new tab using custom admin-defined Group label (e.g., "Teams", "Groups")
- Tab appears only for customers with sharing-enabled memberships
- **Multi-Group Interface**: Display all groups the customer owns (one per variation)
- **Clear Group Identification**: Each group shows its associated membership/variation name

### Customer Group Management Interface
**Multi-Group Dashboard:**
- List all customer's groups with clear identification (e.g., "Pro Annual Group", "Basic Monthly Group")
- Each group shows current member count and limit (e.g., "3/10 members")
- Individual group management panels that can be expanded/collapsed

**Per-Group Management:**
**Join/Leave Group Functionality**:
- **Auto-Added by Default**: Customers are automatically added to groups during order completion
- Special button allowing customers to add/remove themselves from each specific group
-  **Critical**: Customers only access membership-restricted content if they're part of the corresponding group
- **Flexibility**: Even though auto-added, customers can still leave/rejoin groups as needed
- Example: Pro variation (10 subaccounts) = customer auto-added + can manage 9 others OR leave and assign all 10 to others

**Group Administration (per group):**
- Rename individual Groups
- View input fields for each available Subaccount slot in that specific group
- Add email addresses and send invitations
- Visual indicators for invitation status (pending/active/revoked)
- Remove Subaccounts (revokes membership from that group, preserves user account)
- Re-send invitations using same email template
- Revoke pending invitations
- Copy invite links to clipboard

### Subaccount User Experience
When logged in as a Subaccount:
- See Account Billing Manager's name and email
- No group management permissions
- Access to membership-restricted content when group is active

## Email System
-  **Invitation Template**: Single email template for new invitations
-  **Re-invitations**: Use same template as initial invitations
-  **No Email Notifications**: No emails for revocations or admin notifications
-  **Email Requirements**:
- Invitation token for secure access
- Clear call-to-action for account creation/group joining
- Expiration date information
- Group and Customer information

## Database Schema
### Groups Table (share_membership_groups)
```sql
CREATE  TABLE  share_membership_groups (
id INT AUTO_INCREMENT PRIMARY KEY,
customer_user_id BIGINT UNSIGNED NOT NULL,
product_id BIGINT UNSIGNED NOT NULL,
variation_id BIGINT UNSIGNED NOT NULL,
membership_id BIGINT UNSIGNED NOT NULL,
group_name VARCHAR(255) NOT NULL,
max_subaccounts INT  NOT NULL,
current_subaccounts INT  DEFAULT  0,
created_date DATETIME  NOT NULL,
status ENUM('active', 'suspended', 'expired') DEFAULT  'active',
FOREIGN KEY (customer_user_id) REFERENCES wp_users(ID) ON DELETE RESTRICT,
FOREIGN KEY (product_id) REFERENCES wp_posts(ID) ON DELETE RESTRICT,
FOREIGN KEY (variation_id) REFERENCES wp_posts(ID) ON DELETE RESTRICT,
FOREIGN KEY (membership_id) REFERENCES wp_posts(ID) ON DELETE RESTRICT,
INDEX idx_customer (customer_user_id),
INDEX idx_product (product_id),
INDEX idx_variation (variation_id),
INDEX idx_membership (membership_id),
INDEX idx_status (status),
UNIQUE  KEY unique_customer_variation (customer_user_id, variation_id)
);
```

**Foreign Key Constraint Behavior (RESTRICT)**:
- **Purpose**: Prevent accidental deletion of referenced records (users, products, variations, memberships)
- **Admin Impact**: Must manually clean up groups before deleting products/variations
- **Data Integrity**: Ensures no orphaned groups exist
- **Cleanup Process**: Plugin should provide admin tools to bulk delete groups when needed

### Group Members Table (share_membership_group_members)
```sql
CREATE  TABLE  share_membership_group_members (
id INT AUTO_INCREMENT PRIMARY KEY,
group_id INT  NOT NULL,
user_id BIGINT UNSIGNED NULL,
email VARCHAR(255) NOT NULL,
member_type ENUM('customer', 'subaccount') NOT NULL,
status ENUM('active', 'pending', 'revoked') DEFAULT  'pending',
joined_date DATETIME  NULL,
invited_date DATETIME  NOT NULL,
FOREIGN KEY (group_id) REFERENCES share_membership_groups(id) ON DELETE CASCADE,
FOREIGN KEY (user_id) REFERENCES wp_users(ID),
INDEX idx_group (group_id),
INDEX idx_user (user_id),
INDEX idx_email (email),
INDEX idx_status (status)
);
```

### Invitations Table (share_membership_invitations)
```sql
CREATE  TABLE  share_membership_invitations (
id INT AUTO_INCREMENT PRIMARY KEY,
group_id INT  NOT NULL,
email VARCHAR(255) NOT NULL,
invitation_token VARCHAR(255) UNIQUE  NOT NULL,
status ENUM('pending', 'accepted', 'declined', 'expired', 'revoked') DEFAULT  'pending',
sent_date DATETIME  NOT NULL,
expires_date DATETIME  NOT NULL,
accepted_date DATETIME  NULL,
FOREIGN KEY (group_id) REFERENCES share_membership_groups(id) ON DELETE CASCADE,
INDEX idx_token (invitation_token),
INDEX idx_email (email),
INDEX idx_status (status),
INDEX idx_expires (expires_date)
);
```

### Product Settings (wp_postmeta)
**Simplified Variation-Only Meta:**
-  `_variation_enable_sharing` (yes/no) - Per variation sharing enable/disable
-  `_variation_subaccount_limit_type` (fixed/quantity_based) - How subaccounts are allocated
-  `_variation_subaccount_limit` (number) - Fixed limit if using fixed allocation

**Simple Product Meta (for non-variable products):**
-  `_enable_membership_sharing` (yes/no)
-  `_subaccount_limit_type` (fixed/quantity_based)
-  `_subaccount_limit` (number) - Fixed limit if using fixed allocation

### Plugin Options (wp_options)
-  `membership_sharing_group_label_singular`
-  `membership_sharing_group_label_plural`
-  `membership_sharing_subaccount_label_singular`
-  `membership_sharing_subaccount_label_plural`

## Technical Implementation Plan
### Plugin Structure
```
woo-member-share/
├── woo-member-share.php (main plugin file)
├── includes/
│ ├── class-woo-member-share.php (main plugin class)
│ ├── class-database.php (database operations)
│ ├── class-admin.php (admin functionality)
│ ├── class-frontend.php (customer-facing features)
│ ├── class-membership-handler.php (WC Memberships integration)
│ ├── class-email-handler.php (invitation emails)
│ └── class-invitation-handler.php (invitation processing)
├── admin/
│ ├── views/ (admin page templates)
│ └── assets/ (admin CSS/JS)
├── frontend/
│ ├── templates/ (account dashboard templates)
│ └── assets/ (frontend CSS/JS)
│ └── invitation-template.php
└── languages/ (translation files)
```

### Development Phases

**Phase 1: Core Infrastructure**
- Plugin activation/deactivation with dependency checks
- Database table creation and management
- Basic admin settings page for labels
- Simple per-variation sharing settings

**Phase 2: Admin Backend Features**
- Per-variation sharing enable/disable settings
- Fixed vs quantity-based allocation configuration
- Group management page for admins
- Simple variation settings interface

**Phase 3: Customer Frontend**
- My Account tab creation
- Multi-group management interface (one per variation)
- Join/Leave group functionality
- Email invitation system

**Phase 4: Membership Integration**
- Hook into WooCommerce Memberships status changes
- Real-time benefit activation/deactivation per group
- Access control for membership-restricted content
- Direct group-to-membership status synchronization

**Phase 5: Polish & Testing**
- Email template customization
- Error handling and validation
- Security audits
- Performance optimization

### WordPress/WooCommerce Integration Points
**Required Hooks & Filters**:
-  `woocommerce_product_data_panels` - Add product settings
-  `woocommerce_process_product_meta` - Save product settings
-  `woocommerce_variation_options` - Add variation-specific settings
-  `woocommerce_save_product_variation` - Save variation overrides
-  `woocommerce_account_menu_items` - Add My Account tab
-  `woocommerce_order_status_completed` - Process group creation/updates and auto-add customers
-  `init` - Handle invitation acceptance URLs
-  `wp_loaded` - Process group management forms

**Membership Status Synchronization Hooks**:
-  `wc_memberships_user_membership_status_changed` - Handle individual membership status changes
-  `wc_memberships_user_membership_saved` - Handle membership creation and updates
-  `wc_memberships_user_membership_deleted` - Handle membership deletions
-  `wc_memberships_grant_membership_access_from_order` - Handle new membership grants from orders
-  `wc_memberships_is_user_active_member` - Filter membership access checks for subaccounts

**Caching Strategy**:
- **Membership Status Cache**: Cache individual user membership status for 1 hour using WordPress transients
- **Group Membership Cache**: Store group member lists in object cache, invalidate on changes
- **Cache Keys**: Use format `wms_user_membership_{user_id}_{membership_id}` and `wms_group_members_{group_id}`
- **Cache Invalidation**: Clear relevant caches on membership status changes, group modifications
- **Bulk Operations**: Batch cache invalidation for performance during bulk membership changes

**Database Queries Optimization**:
- Index frequently queried fields (user_id, group_id, status, variation_id, membership_id)
- Use prepared statements with proper parameter binding
- Implement query result caching for expensive lookups
- Batch process membership status updates to avoid N+1 queries
- Use EXISTS clauses instead of JOIN for membership status checks

### Security Measures
**Authentication & Authorization**:
- Nonce verification for all form submissions
- Capability checks for admin functions (`manage_woocommerce`)
- User ownership verification for group operations
- Secure token generation for invitations (detailed specifications below)

**Invitation Token Security**:
- **Token Format**: 64 characters, alphanumeric only (A-Z, a-z, 0-9)
- **Generation Method**: wp_generate_password(64, false, false) for alphanumeric-only output
- **Expiration Duration**: 30 days from creation (hardcoded, not configurable)
- **Collision Prevention**: Database UNIQUE constraint + regeneration on duplicate (maximum 3 attempts)
- **Storage**: Store hashed version in database, send plain token via email
- **Validation**: Always verify token exists, hasn't expired, and belongs to correct group

**Input Validation**:
- Sanitize all user inputs
- Validate email formats using WordPress is_email()
- Check invitation token format (exactly 64 alphanumeric characters)
- Prevent self-invitation attempts
- Limit group name length (255 characters) and sanitize for XSS
- Validate group member limits against product configuration

**Data Protection**:
- Store invitation tokens in hashed format using wp_hash()
- Implement RESTRICT foreign key constraints (prevent cascading deletions)
- Escape all output data using WordPress escape functions
- Use WordPress prepared statements for all database queries
- Implement rate limiting on group operations (prevent spam)

### Error Handling & Edge Cases

**Critical Error Scenarios**:
- WooCommerce Memberships plugin deactivation
- Database connection failures
- Email delivery failures
- Invalid invitation tokens
- Membership status sync failures

**Membership Sync Failure Recovery (Exponential Backoff)**:
- **Purpose**: Handle temporary WooCommerce Memberships API failures gracefully
- **Retry Schedule**: 
  - First retry: 2 minutes after initial failure
  - Second retry: 10 minutes after first retry
  - Third retry: 1 hour after second retry  
  - Fourth retry: 6 hours after third retry
  - Final: Mark as permanent failure and log for manual review
- **Implementation**: Use WordPress wp_schedule_single_event() for retry scheduling
- **Logging**: Record all retry attempts with timestamps and error details
- **Admin Notification**: Display admin notice for permanent failures requiring manual intervention

**User Experience Error Handling**:
- Invitation limits exceeded
- Invalid email formats
- Duplicate email invitations
- Accessing expired invitations
- Group name conflicts
- Self-invitation attempts
- Subaccount already has higher-level membership
- Variation-specific limit conflicts
- Multiple variation ownership edge cases

**Variable Product Edge Cases**:
- Customer downgrades from higher to lower variation
- Simultaneous ownership of conflicting variations
- Variation deletion with active groups
- Parent product settings changed affecting variations
- Group limit reduction requiring member removal

**Admin Error Scenarios**:
- Product deletion with active groups
- User account deletion with active memberships
- Database table corruption
- Email server configuration issues

### Performance Considerations
**Database Optimization**:
- Proper indexing on foreign keys and frequently queried fields
- Use of prepared statements for all queries
- Batch operations for bulk updates
- Regular cleanup of expired invitations

**Caching Strategy**:
- Cache group membership status for active users
- Store frequently accessed group data in object cache
- Implement cache invalidation on membership changes
- Use transients for expensive database queries

**Frontend Performance**:
- Lazy load group member lists
- AJAX for invitation management
- Minimize database queries on My Account page
- Optimize email template rendering

### Testing Requirements
**Unit Testing**:
- Database operations
- Invitation token generation
- Email functionality
- Access control logic

**Integration Testing**:
- WooCommerce Memberships compatibility
- Product variation handling
- Membership status synchronization
- Email delivery

**User Acceptance Testing**:
- Complete customer workflow
- Admin management features
- Error handling scenarios
- Mobile responsiveness

## Implementation Notes
1.  **Plugin Activation**: Check for WooCommerce and WooCommerce Memberships before activation
2.  **Database Updates**: Use proper versioning for schema updates
3.  **Translation Ready**: All strings must be internationalized
4.  **Multisite Compatible**: Consider multisite WordPress installations
5.  **GDPR Compliance**: Handle user data deletion requests appropriately
6.  **Performance Monitoring**: Log slow queries and optimize accordingly

## Success Criteria
- Seamless integration with WooCommerce Memberships
- Intuitive user interface requiring no documentation
- Zero downtime membership benefit transitions
- Secure invitation system with no token collisions
- Scalable to handle hundreds of groups per site
- Compatible with popular WooCommerce themes
- Passes WordPress plugin review guidelines
