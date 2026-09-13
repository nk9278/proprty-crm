CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempts INT DEFAULT 1,
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(action, ip_address)
);

CREATE TABLE IF NOT EXISTS otps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mobile VARCHAR(20) NOT NULL,
    otp_code VARCHAR(10) NOT NULL,
    action VARCHAR(50) NOT NULL,
    attempts INT DEFAULT 0,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    status ENUM('Active', 'Trial', 'Expired', 'Suspended', 'Archived') DEFAULT 'Trial',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tenant_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE(tenant_id, setting_key)
);

CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT
);

CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT
);

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT,
    name VARCHAR(255) NOT NULL,
    mobile VARCHAR(20) NOT NULL,
    email VARCHAR(255),
    pin_hash VARCHAR(255) NOT NULL,
    role_id INT,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL,
    UNIQUE(tenant_id, mobile)
);

CREATE TABLE IF NOT EXISTS user_roles (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS property_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS property_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(100) NOT NULL,
    FOREIGN KEY (category_id) REFERENCES property_categories(id) ON DELETE CASCADE,
    UNIQUE(category_id, name)
);

CREATE TABLE IF NOT EXISTS bhk_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS property_facing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS amenities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    developer VARCHAR(255),
    rera_number VARCHAR(100),
    rera_authority VARCHAR(100),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    country VARCHAR(100),
    pincode VARCHAR(20),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    construction_status VARCHAR(100),
    possession_date DATE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS project_towers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    total_floors INT,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE(project_id, name)
);

CREATE TABLE IF NOT EXISTS project_floors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tower_id INT NOT NULL,
    floor_number VARCHAR(50) NOT NULL,
    FOREIGN KEY (tower_id) REFERENCES project_towers(id) ON DELETE CASCADE,
    UNIQUE(tower_id, floor_number)
);

CREATE TABLE IF NOT EXISTS properties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    project_id INT NULL,
    category_id INT NOT NULL,
    type_id INT NOT NULL,
    bhk_id INT NULL,
    facing_id INT NULL,
    name VARCHAR(255) NOT NULL,
    purpose ENUM('For Sale', 'For Rent', 'For Lease', 'For Resale', 'For Investment', 'Joint Venture', 'Auction') NOT NULL,
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    pincode VARCHAR(20),
    carpet_area DECIMAL(10, 2),
    builtup_area DECIMAL(10, 2),
    super_builtup_area DECIMAL(10, 2),
    plot_area DECIMAL(10, 2),
    base_price DECIMAL(15, 2),
    description TEXT,
    status ENUM('Available', 'Hold', 'Blocked', 'Token Received', 'Booked', 'Sold', 'Cancelled', 'Released', 'Archived') DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES property_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (type_id) REFERENCES property_types(id) ON DELETE CASCADE,
    FOREIGN KEY (bhk_id) REFERENCES bhk_types(id) ON DELETE SET NULL,
    FOREIGN KEY (facing_id) REFERENCES property_facing(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS property_units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    floor_id INT NULL,
    unit_number VARCHAR(100) NOT NULL,
    carpet_area DECIMAL(10, 2),
    base_price DECIMAL(15, 2),
    plc DECIMAL(15, 2) DEFAULT 0,
    floor_rise DECIMAL(15, 2) DEFAULT 0,
    parking_charge DECIMAL(15, 2) DEFAULT 0,
    maintenance DECIMAL(15, 2) DEFAULT 0,
    other_charges DECIMAL(15, 2) DEFAULT 0,
    gst DECIMAL(15, 2) DEFAULT 0,
    discount DECIMAL(15, 2) DEFAULT 0,
    final_price DECIMAL(15, 2) DEFAULT 0,
    status ENUM('Available', 'Hold', 'Blocked', 'Token Received', 'Booked', 'Sold', 'Cancelled', 'Released') DEFAULT 'Available',
    hold_expires_at TIMESTAMP NULL DEFAULT NULL,
    held_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    FOREIGN KEY (floor_id) REFERENCES project_floors(id) ON DELETE SET NULL,
    FOREIGN KEY (held_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE(property_id, floor_id, unit_number)
);

CREATE TABLE IF NOT EXISTS property_amenity_map (
    property_id INT NOT NULL,
    amenity_id INT NOT NULL,
    PRIMARY KEY (property_id, amenity_id),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    FOREIGN KEY (amenity_id) REFERENCES amenities(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS property_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    media_type ENUM('Image', 'Video', 'Floor Plan', 'Brochure', 'Document') NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100),
    is_primary BOOLEAN DEFAULT FALSE,
    uploaded_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS lead_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS lead_statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    lead_id INT NULL,
    name VARCHAR(255) NOT NULL,
    mobile VARCHAR(20) NOT NULL,
    alternate_mobile VARCHAR(20),
    whatsapp VARCHAR(20),
    email VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    country VARCHAR(100),
    address TEXT,
    occupation VARCHAR(255),
    company VARCHAR(255),
    source_id INT,
    assigned_to INT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (source_id) REFERENCES lead_sources(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    -- Note: lead_id FK will be added via ALTER TABLE to avoid circular dependency initially
);

CREATE TABLE IF NOT EXISTS pipeline_stages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    sort_order INT DEFAULT 0,
    is_system TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    mobile VARCHAR(20) NOT NULL,
    alternate_mobile VARCHAR(20),
    whatsapp VARCHAR(20),
    email VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    country VARCHAR(100),
    address TEXT,
    source_id INT,
    campaign VARCHAR(255),
    ad VARCHAR(255),
    utm_source VARCHAR(100),
    utm_medium VARCHAR(100),
    utm_campaign VARCHAR(100),
    utm_content VARCHAR(100),
    utm_term VARCHAR(100),
    requirement TEXT,
    property_category_id INT,
    property_type_id INT,
    bhk_id INT,
    budget_min DECIMAL(15, 2),
    budget_max DECIMAL(15, 2),
    preferred_location VARCHAR(255),
    purpose ENUM('For Sale', 'For Rent', 'For Lease', 'For Resale', 'For Investment', 'Joint Venture', 'Auction'),
    financing VARCHAR(100),
    lead_score INT DEFAULT 0,
    lead_temperature ENUM('Hot', 'Warm', 'Cold') DEFAULT 'Cold',
    status_id INT,
    pipeline_stage_id INT NULL,
    assigned_team INT,
    assigned_to INT,
    created_by INT,
    last_contact TIMESTAMP NULL DEFAULT NULL,
    next_followup TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    customer_id INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (source_id) REFERENCES lead_sources(id) ON DELETE SET NULL,
    FOREIGN KEY (status_id) REFERENCES lead_statuses(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (pipeline_stage_id) REFERENCES pipeline_stages(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (property_category_id) REFERENCES property_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (property_type_id) REFERENCES property_types(id) ON DELETE SET NULL,
    FOREIGN KEY (bhk_id) REFERENCES bhk_types(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS lead_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    user_id INT NOT NULL,
    assigned_by INT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS lead_sla (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    user_id INT NOT NULL,
    assigned_time TIMESTAMP NOT NULL,
    sla_deadline TIMESTAMP NOT NULL,
    first_response_time TIMESTAMP NULL DEFAULT NULL,
    status ENUM('Pending', 'Met', 'Breached') DEFAULT 'Pending',
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS lead_assignment_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    previous_user_id INT,
    new_user_id INT NOT NULL,
    reassigned_by INT,
    reassignment_reason TEXT,
    assignment_method VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (previous_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (new_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reassigned_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS lead_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS followup_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS followups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    lead_id INT NULL,
    customer_id INT NULL,
    user_id INT NOT NULL,
    followup_type_id INT,
    followup_date DATE NOT NULL,
    followup_time TIME,
    priority ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
    notes TEXT,
    outcome VARCHAR(255),
    status ENUM('Pending', 'Completed', 'Overdue', 'Cancelled', 'Missed', 'Rescheduled') DEFAULT 'Pending',
    next_followup_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (followup_type_id) REFERENCES followup_types(id) ON DELETE SET NULL,
    FOREIGN KEY (next_followup_id) REFERENCES followups(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    task_type VARCHAR(100),
    priority ENUM('Low', 'Medium', 'High', 'Urgent') DEFAULT 'Medium',
    assigned_user INT NULL,
    related_lead INT NULL,
    related_customer INT NULL,
    related_property INT NULL,
    related_project INT NULL,
    due_date DATE NOT NULL,
    due_time TIME,
    status ENUM('Pending', 'In Progress', 'Completed', 'Cancelled', 'Overdue') DEFAULT 'Pending',
    completion_date TIMESTAMP NULL DEFAULT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_user) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (related_lead) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (related_customer) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (related_property) REFERENCES properties(id) ON DELETE CASCADE,
    FOREIGN KEY (related_project) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS lead_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    user_id INT NOT NULL,
    note TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS lead_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(20) DEFAULT '#cccccc',
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE(tenant_id, name)
);

CREATE TABLE IF NOT EXISTS lead_tag_map (
    lead_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (lead_id, tag_id),
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES lead_tags(id) ON DELETE CASCADE
);ALTER TABLE customers ADD FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL;
CREATE TABLE IF NOT EXISTS property_shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    lead_id INT NULL,
    customer_id INT NULL,
    shared_by INT NOT NULL,
    channel ENUM('WhatsApp', 'SMS', 'Email', 'Link', 'CRM Internal') NOT NULL,
    message TEXT,
    status ENUM('Sent', 'Delivered', 'Read', 'Failed') DEFAULT 'Sent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS property_share_items (
    share_id INT NOT NULL,
    property_id INT NOT NULL,
    unit_id INT,
    PRIMARY KEY (share_id, property_id),
    FOREIGN KEY (share_id) REFERENCES property_shares(id) ON DELETE CASCADE,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES property_units(id) ON DELETE SET NULL
);

-- ==========================================================
-- PHASE 10: SITE VISITS
-- ==========================================================
CREATE TABLE IF NOT EXISTS site_visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    lead_id INT NULL,
    customer_id INT NULL,
    project_id INT NULL,
    property_id INT NULL,
    unit_id INT NULL,
    salesperson_id INT NOT NULL,
    scheduled_date DATE NOT NULL,
    scheduled_time TIME NOT NULL,
    visitor_count INT DEFAULT 1,
    visitor_names VARCHAR(255) NULL,
    status ENUM('Scheduled', 'Confirmed', 'Rescheduled', 'Completed', 'Cancelled', 'No-Show') DEFAULT 'Scheduled',
    check_in_time DATETIME NULL,
    check_out_time DATETIME NULL,
    feedback TEXT NULL,
    interest_level ENUM('High', 'Medium', 'Low') NULL,
    notes TEXT NULL,
    next_action VARCHAR(255) NULL,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
    FOREIGN KEY (unit_id) REFERENCES property_units(id) ON DELETE SET NULL,
    FOREIGN KEY (salesperson_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==========================================================
-- PHASE 12: BOOKINGS & COST SHEETS
-- ==========================================================
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    booking_reference VARCHAR(100) NOT NULL,
    lead_id INT NULL,
    customer_id INT NOT NULL,
    project_id INT NULL,
    property_id INT NULL,
    unit_id INT NOT NULL,
    salesperson_id INT NOT NULL,
    broker_id INT NULL,
    booking_date DATE NOT NULL,
    status ENUM('Draft', 'Token Pending', 'Token Received', 'Booked', 'Cancelled', 'Closed') DEFAULT 'Draft',
    notes TEXT,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
    FOREIGN KEY (unit_id) REFERENCES property_units(id) ON DELETE CASCADE,
    FOREIGN KEY (salesperson_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (broker_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS booking_cost_sheets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    booking_id INT NOT NULL,
    base_price DECIMAL(15,2) DEFAULT 0.00,
    plc DECIMAL(15,2) DEFAULT 0.00,
    floor_rise DECIMAL(15,2) DEFAULT 0.00,
    parking DECIMAL(15,2) DEFAULT 0.00,
    club_charges DECIMAL(15,2) DEFAULT 0.00,
    maintenance DECIMAL(15,2) DEFAULT 0.00,
    edc DECIMAL(15,2) DEFAULT 0.00,
    idc DECIMAL(15,2) DEFAULT 0.00,
    gst DECIMAL(15,2) DEFAULT 0.00,
    other_charges DECIMAL(15,2) DEFAULT 0.00,
    discount DECIMAL(15,2) DEFAULT 0.00,
    final_amount DECIMAL(15,2) DEFAULT 0.00,
    token_amount DECIMAL(15,2) DEFAULT 0.00,
    amount_received DECIMAL(15,2) DEFAULT 0.00,
    outstanding_amount DECIMAL(15,2) DEFAULT 0.00,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================================
-- PHASE 13: PAYMENTS & COLLECTIONS
-- ==========================================================
CREATE TABLE IF NOT EXISTS payment_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    booking_id INT NOT NULL,
    plan_type ENUM('Construction-linked', 'Time-linked', 'Custom') DEFAULT 'Custom',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payment_milestones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    payment_plan_id INT NOT NULL,
    milestone_name VARCHAR(255) NOT NULL,
    percentage DECIMAL(5,2) DEFAULT 0.00,
    amount DECIMAL(15,2) NOT NULL,
    due_date DATE NULL,
    status ENUM('Pending', 'Due', 'Partially Paid', 'Paid', 'Overdue', 'Cancelled') DEFAULT 'Pending',

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_plan_id) REFERENCES payment_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    booking_id INT NOT NULL,
    milestone_id INT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_mode ENUM('Cash', 'Cheque', 'Bank Transfer', 'Credit Card', 'Online', 'Other') NOT NULL,
    transaction_reference VARCHAR(255) NULL,
    receipt_number VARCHAR(100) NULL,
    status ENUM('Pending', 'Completed', 'Failed', 'Refunded') DEFAULT 'Completed',
    notes TEXT,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (milestone_id) REFERENCES payment_milestones(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================================
-- PHASE 14: CHANNEL PARTNERS & COMMISSIONS
-- ==========================================================

CREATE TABLE IF NOT EXISTS commission_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    rule_type ENUM('Percentage', 'Fixed', 'Slab', 'Property', 'Project', 'Broker', 'Salesperson') NOT NULL,
    value DECIMAL(10,2) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS channel_partners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255) NOT NULL,
    mobile VARCHAR(20) NOT NULL,
    whatsapp VARCHAR(20) NULL,
    email VARCHAR(255) NULL,
    address TEXT NULL,
    gst_number VARCHAR(50) NULL,
    pan_number VARCHAR(20) NULL,
    rera_number VARCHAR(100) NULL,
    commission_rule_id INT NULL,
    status ENUM('Active', 'Inactive', 'Suspended', 'Archived') DEFAULT 'Active',
    notes TEXT NULL,
    assigned_manager_id INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (commission_rule_id) REFERENCES commission_rules(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_manager_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS commissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    booking_id INT NOT NULL,
    channel_partner_id INT NULL,
    salesperson_id INT NULL,
    commission_rule_id INT NULL,
    base_amount DECIMAL(15,2) NOT NULL, -- Total value the commission is calculated on
    commission_amount DECIMAL(15,2) NOT NULL,
    status ENUM('Estimated', 'Eligible', 'Pending Approval', 'Approved', 'Partially Paid', 'Paid', 'Cancelled', 'Reversed') DEFAULT 'Estimated',
    amount_paid DECIMAL(15,2) DEFAULT 0.00,
    outstanding_amount DECIMAL(15,2) DEFAULT 0.00,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (channel_partner_id) REFERENCES channel_partners(id) ON DELETE SET NULL,
    FOREIGN KEY (salesperson_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (commission_rule_id) REFERENCES commission_rules(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS commission_payouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    commission_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payout_date DATE NOT NULL,
    payment_method ENUM('Bank Transfer', 'Cheque', 'Cash', 'Other') NOT NULL,
    transaction_reference VARCHAR(255) NULL,
    status ENUM('Pending', 'Approved', 'Paid', 'Reversed', 'Cancelled') DEFAULT 'Paid',
    notes TEXT,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (commission_id) REFERENCES commissions(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================================
-- PHASE 15: WHATSAPP / COMMUNICATION ARCHITECTURE
-- ==========================================================
CREATE TABLE IF NOT EXISTS whatsapp_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    salesperson_id INT NULL,
    provider_name VARCHAR(100) NOT NULL, -- e.g. Twilio, Meta, Gupshup (Architecture foundation)
    phone_number VARCHAR(50) NOT NULL,
    api_key VARCHAR(255) NULL,
    api_secret VARCHAR(255) NULL,
    status ENUM('Active', 'Disconnected', 'Unconfigured') DEFAULT 'Unconfigured',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (salesperson_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS communication_consents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    customer_id INT NULL,
    lead_id INT NULL,
    mobile VARCHAR(20) NOT NULL,
    has_consent TINYINT(1) DEFAULT 1,
    opt_in_timestamp DATETIME NULL,
    opt_in_source VARCHAR(100) NULL,
    opt_out_timestamp DATETIME NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS message_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS whatsapp_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    whatsapp_account_id INT NOT NULL,
    lead_id INT NULL,
    customer_id INT NULL,
    sender_id INT NOT NULL,
    direction ENUM('Outbound', 'Inbound') NOT NULL,
    message_type ENUM('Text', 'Template', 'Media', 'Location', 'Link') DEFAULT 'Text',
    content TEXT NULL,
    media_url VARCHAR(255) NULL,
    delivery_status ENUM('Sent', 'Delivered', 'Read', 'Failed') DEFAULT 'Sent',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (whatsapp_account_id) REFERENCES whatsapp_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
