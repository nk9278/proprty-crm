INSERT IGNORE INTO roles (name, description) VALUES
('Super Admin', 'System Administrator'),
('Tenant Owner', 'Full Access to Tenant'),
('Admin', 'Tenant Administrator'),
('Sales Manager', 'Manager for Sales Team'),
('Team Leader', 'Team Leader'),
('Salesman', 'Sales Executive'),
('Agent', 'Agent or Channel Partner'),
('Customer', 'Property Buyer/Customer');

INSERT IGNORE INTO permissions (name, description) VALUES
('leads.view', 'View Leads'),
('leads.create', 'Create Leads'),
('leads.edit', 'Edit Leads'),
('leads.delete', 'Delete Leads'),
('leads.assign', 'Assign Leads'),
('leads.reassign', 'Reassign Leads'),
('properties.view', 'View Properties'),
('properties.create', 'Create Properties'),
('properties.edit', 'Edit Properties'),
('properties.delete', 'Delete Properties'),
('customers.view', 'View Customers'),
('customers.create', 'Create Customers'),
('customers.edit', 'Edit Customers'),
('customers.delete', 'Delete Customers'),
('customers.convert', 'Convert Lead to Customer'),
('bookings.view', 'View Bookings'),
('bookings.create', 'Create Bookings'),
('bookings.cancel', 'Cancel Bookings'),
('payments.view', 'View Payments'),
('payments.create', 'Create Payments'),
('payments.edit', 'Edit Payments'),
('commission.view', 'View Commissions'),
('commission.approve', 'Approve Commissions'),
('commission.pay', 'Pay Commissions'),
('reports.view', 'View Reports');

-- Seed basic role permissions (Example for Salesman)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'Salesman' AND p.name IN ('leads.view', 'leads.create', 'leads.edit', 'properties.view', 'bookings.view', 'payments.view', 'customers.view', 'customers.convert', 'customers.create', 'customers.edit');

-- Example for Admin (gets everything except what Super Admin gets specifically)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'Admin';

INSERT IGNORE INTO property_categories (name) VALUES
('Residential'), ('Commercial'), ('Land'), ('Plot'), ('Rental'), ('Lease'), ('Resale'), ('Investment'), ('Other');

INSERT IGNORE INTO property_facing (name) VALUES
('East'), ('West'), ('North'), ('South'), ('North-East'), ('North-West'), ('South-East'), ('South-West');

INSERT IGNORE INTO amenities (name) VALUES
('Swimming Pool'), ('Gym'), ('Club House'), ('Garden'), ('Park'), ('Security'), ('CCTV'), ('Lift'), ('Power Backup'), ('Parking'),
('Visitor Parking'), ('Kids Play Area'), ('Jogging Track'), ('Sports Area'), ('Community Hall'), ('Party Hall'), ('Indoor Games'),
('Outdoor Games'), ('Temple'), ('EV Charging'), ('Fire Safety'), ('Water Supply'), ('Gated Security'), ('Intercom'),
('Security Guard'), ('Rainwater Harvesting'), ('Solar Power'), ('Sewage Treatment Plant'), ('Visitor Management'),
('Cafeteria'), ('Rooftop Garden');

INSERT IGNORE INTO property_types (category_id, name) VALUES
((SELECT id FROM property_categories WHERE name='Residential'), 'Studio Apartment'),
((SELECT id FROM property_categories WHERE name='Residential'), '1 BHK Flat'),
((SELECT id FROM property_categories WHERE name='Residential'), '2 BHK Flat'),
((SELECT id FROM property_categories WHERE name='Residential'), '3 BHK Flat'),
((SELECT id FROM property_categories WHERE name='Residential'), 'Villa'),
((SELECT id FROM property_categories WHERE name='Commercial'), 'Office Space'),
((SELECT id FROM property_categories WHERE name='Commercial'), 'Retail Shop');

INSERT IGNORE INTO bhk_types (name) VALUES
('Studio'), ('1 RK'), ('2 RK'), ('3 RK'), ('1 BHK'), ('1.5 BHK'), ('2 BHK'), ('2.5 BHK'), ('3 BHK'), ('3.5 BHK'), ('4 BHK');

INSERT IGNORE INTO lead_sources (name) VALUES
('Instagram'), ('Facebook'), ('Meta Ads'), ('Google Ads'), ('Google Search'), ('Website'), ('WhatsApp'), ('Property Portal'), ('Walk-in'), ('Referral');

INSERT IGNORE INTO lead_statuses (name) VALUES
('New'), ('Unassigned'), ('Assigned'), ('Contact Pending'), ('Called'), ('Connected'), ('Interested'), ('Site Visit Planned'), ('Site Visit Completed'), ('Negotiation'), ('Token Received'), ('Booked'), ('Sold'), ('Lost');

INSERT IGNORE INTO followup_types (name) VALUES
('Call'), ('WhatsApp'), ('Meeting'), ('Site Visit'), ('Office Visit'), ('Video Call'), ('Email'), ('Payment Follow-up'), ('Document Follow-up'), ('Negotiation'), ('Other');
