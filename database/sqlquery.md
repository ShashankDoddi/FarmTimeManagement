CREATE TABLE `admin` (
  `admin_id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `permission_level` enum('SuperAdmin','PayrollAdmin','RosterAdmin','SiteAdmin') NOT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `status` enum('Active','Inactive','Suspended') NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `admin`
--

INSERT INTO `admin` (`admin_id`, `site_id`, `username`, `password_hash`, `permission_level`, `contact_number`, `email`, `status`, `created_at`) VALUES
(1, 1, 'superadmin01', 'hashed_password_001', 'SuperAdmin', '0400000001', 'superadmin@farm.com', 'Active', '2026-04-15 22:41:34'),
(2, 1, 'rosteradmin01', 'hashed_password_002', 'RosterAdmin', '0400000002', 'rosteradmin@farm.com', 'Active', '2026-04-15 22:41:34'),
(3, 2, 'payroll01', 'hashed_password_003', 'PayrollAdmin', '0400000003', 'payroll@farm.com', 'Active', '2026-04-15 22:41:34');

-- --------------------------------------------------------

--
-- 表的结构 `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(10) UNSIGNED NOT NULL,
  `roster_id` int(10) UNSIGNED NOT NULL,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `device_id` int(10) UNSIGNED DEFAULT NULL,
  `clock_in` datetime NOT NULL,
  `clock_out` datetime DEFAULT NULL,
  `clock_in_method` enum('biometric','card','manual_override','mobile_app') NOT NULL,
  `clock_out_method` enum('biometric','card','manual_override','mobile_app') DEFAULT NULL,
  `attendance_status` enum('present','late','absent','incomplete','manual_review') NOT NULL DEFAULT 'present',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ;

--
-- 转存表中的数据 `attendance`
--

INSERT INTO `attendance` (`attendance_id`, `roster_id`, `staff_id`, `device_id`, `clock_in`, `clock_out`, `clock_in_method`, `clock_out_method`, `attendance_status`, `created_at`) VALUES
(1, 1, 1, 1, '2024-04-01 08:00:00', '2024-04-01 16:05:00', 'biometric', 'biometric', 'present', '2026-04-15 22:43:49'),
(2, 2, 2, 2, '2024-04-01 08:20:00', '2024-04-01 16:00:00', 'card', 'card', 'late', '2026-04-15 22:43:49');

-- --------------------------------------------------------

--
-- 表的结构 `attendance_breaks`
--

CREATE TABLE `attendance_breaks` (
  `break_id` int(10) UNSIGNED NOT NULL,
  `attendance_id` int(10) UNSIGNED NOT NULL,
  `break_start` datetime NOT NULL,
  `break_end` datetime NOT NULL,
  `break_reason` enum('Meal','Rest','Medical','Personal','Other') NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL
) ;

--
-- 转存表中的数据 `attendance_breaks`
--

INSERT INTO `attendance_breaks` (`break_id`, `attendance_id`, `break_start`, `break_end`, `break_reason`, `note`, `created_at`, `created_by`) VALUES
(1, 1, '2024-04-01 12:00:00', '2024-04-01 12:30:00', 'Meal', 'Lunch break', '2026-04-15 22:44:00', 2),
(2, 2, '2024-04-01 12:15:00', '2024-04-01 12:45:00', 'Meal', 'Lunch break', '2026-04-15 22:44:00', 2);

-- --------------------------------------------------------

--
-- 表的结构 `audit_logs`
--

CREATE TABLE `audit_logs` (
  `audit_log_id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED NOT NULL,
  `action_type` enum('INSERT','UPDATE','DELETE','OVERRIDE_CLOCK','APPROVE','REJECT') NOT NULL,
  `target_table` varchar(100) NOT NULL,
  `target_id` int(10) UNSIGNED NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `source_channel` varchar(50) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 表的结构 `biometric_registrations`
--

CREATE TABLE `biometric_registrations` (
  `biometric_id` int(10) UNSIGNED NOT NULL,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `device_id` int(10) UNSIGNED NOT NULL,
  `biometric_data_ref` varchar(150) NOT NULL,
  `card_number` varchar(100) NOT NULL,
  `registered_date` date NOT NULL,
  `status` enum('active','inactive','revoked','pending') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `biometric_registrations`
--

INSERT INTO `biometric_registrations` (`biometric_id`, `staff_id`, `device_id`, `biometric_data_ref`, `card_number`, `registered_date`, `status`, `created_at`) VALUES
(1, 1, 1, 'BIO_REF_001', 'CARD001', '2024-01-20', 'active', '2026-04-15 22:43:31'),
(2, 2, 2, 'BIO_REF_002', 'CARD002', '2024-03-05', 'active', '2026-04-15 22:43:31');

-- --------------------------------------------------------

--
-- 表的结构 `contracts`
--

CREATE TABLE `contracts` (
  `contract_id` int(10) UNSIGNED NOT NULL,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `contract_type` enum('Full Time','Part Time','Casual') NOT NULL,
  `pay_type` enum('Hourly','Salary') NOT NULL DEFAULT 'Hourly',
  `standard_pay_rate` decimal(10,2) NOT NULL,
  `overtime_pay_rate` decimal(10,2) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `standard_weekly_hours` decimal(5,2) NOT NULL,
  `annual_leave_rate` decimal(6,4) NOT NULL DEFAULT 0.0000,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL
) ;

--
-- 转存表中的数据 `contracts`
--

INSERT INTO `contracts` (`contract_id`, `staff_id`, `contract_type`, `pay_type`, `standard_pay_rate`, `overtime_pay_rate`, `start_date`, `end_date`, `standard_weekly_hours`, `annual_leave_rate`, `is_active`, `created_at`, `created_by`) VALUES
(1, 1, 'Full Time', 'Hourly', 30.00, 45.00, '2024-01-15', NULL, 38.00, 2.9231, 1, '2026-04-15 22:42:04', 1),
(2, 2, 'Part Time', 'Hourly', 28.00, 42.00, '2024-03-01', NULL, 20.00, 1.5385, 1, '2026-04-15 22:42:04', 1);

-- --------------------------------------------------------

--
-- 表的结构 `devices`
--

CREATE TABLE `devices` (
  `device_id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `device_type` enum('biometric_scanner','card_reader','tablet','kiosk') NOT NULL,
  `location` varchar(150) NOT NULL,
  `device_name` varchar(100) NOT NULL,
  `device_number` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `devices`
--

INSERT INTO `devices` (`device_id`, `site_id`, `device_type`, `location`, `device_name`, `device_number`, `created_at`) VALUES
(1, 1, 'biometric_scanner', 'Entrance Gate', 'Scanner A1', 'DEV001', '2026-04-15 22:43:21'),
(2, 1, 'card_reader', 'Warehouse', 'Card Reader B1', 'DEV002', '2026-04-15 22:43:21'),
(3, 2, 'tablet', 'Field Office', 'Tablet C1', 'DEV003', '2026-04-15 22:43:21');

-- --------------------------------------------------------

--
-- 表的结构 `exceptions`
--

CREATE TABLE `exceptions` (
  `exception_id` int(10) UNSIGNED NOT NULL,
  `attendance_id` int(10) UNSIGNED NOT NULL,
  `exception_type` enum('late_clock_in','early_clock_out','missed_clock_out','missed_break','unrostered_attendance','overtime','manual_override') NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `exceptions`
--

INSERT INTO `exceptions` (`exception_id`, `attendance_id`, `exception_type`, `description`, `created_at`, `created_by`) VALUES
(1, 2, 'late_clock_in', 'Arrived 20 minutes late', '2026-04-15 22:44:13', 2);

-- --------------------------------------------------------

--
-- 表的结构 `leave_records`
--

CREATE TABLE `leave_records` (
  `leave_id` int(10) UNSIGNED NOT NULL,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `leave_type` enum('annual_leave','personal_leave','unpaid_leave','other') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `hours` decimal(6,2) NOT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `note` varchar(255) DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 替换视图以便查看 `monthly_cost_summary`
-- （参见下面的实际视图）
--
CREATE TABLE `monthly_cost_summary` (
`summary_month` varchar(7)
,`total_pay` decimal(34,2)
,`total_net_pay` decimal(34,2)
,`total_tax` decimal(34,2)
,`total_super` decimal(43,8)
);

-- --------------------------------------------------------

--
-- 表的结构 `payslips`
--

CREATE TABLE `payslips` (
  `payslip_id` int(10) UNSIGNED NOT NULL,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `pay_period_id` int(10) UNSIGNED NOT NULL,
  `period_start_date` date NOT NULL,
  `period_end_date` date NOT NULL,
  `pay_date` date NOT NULL,
  `ytd_gross_pay` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_hours` decimal(8,2) NOT NULL DEFAULT 0.00,
  `total_pay` decimal(12,2) NOT NULL DEFAULT 0.00,
  `super_rate` decimal(5,2) NOT NULL DEFAULT 10.00,
  `tax_rate` decimal(5,2) DEFAULT NULL,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `night_pay` decimal(12,2) NOT NULL DEFAULT 0.00,
  `annual_leave_accrued` decimal(8,2) NOT NULL DEFAULT 0.00,
  `annual_leave_used` decimal(8,2) NOT NULL DEFAULT 0.00,
  `annual_leave_balance` decimal(8,2) NOT NULL DEFAULT 0.00,
  `net_pay` decimal(12,2) NOT NULL DEFAULT 0.00,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `generated_by` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `payslips`
--

INSERT INTO `payslips` (`payslip_id`, `staff_id`, `pay_period_id`, `period_start_date`, `period_end_date`, `pay_date`, `ytd_gross_pay`, `total_hours`, `total_pay`, `super_rate`, `tax_rate`, `tax_amount`, `night_pay`, `annual_leave_accrued`, `annual_leave_used`, `annual_leave_balance`, `net_pay`, `generated_at`, `generated_by`) VALUES
(1, 1, 1, '2024-04-01', '2024-04-07', '2024-04-08', 1000.00, 40.00, 1200.00, 10.00, NULL, 200.00, 0.00, 5.00, 0.00, 20.00, 1000.00, '2026-04-15 22:47:42', 3);

-- --------------------------------------------------------

--
-- 表的结构 `pay_periods`
--

CREATE TABLE `pay_periods` (
  `pay_period_id` int(10) UNSIGNED NOT NULL,
  `period_name` varchar(100) NOT NULL,
  `period_start_date` date NOT NULL,
  `period_end_date` date NOT NULL,
  `pay_date` date NOT NULL,
  `status` enum('open','processing','closed') NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `pay_periods`
--

INSERT INTO `pay_periods` (`pay_period_id`, `period_name`, `period_start_date`, `period_end_date`, `pay_date`, `status`, `created_at`, `created_by`) VALUES
(1, 'April Week 1', '2024-04-01', '2024-04-07', '2024-04-08', 'open', '2026-04-15 22:47:29', 3);

-- --------------------------------------------------------

--
-- 表的结构 `roles`
--

CREATE TABLE `roles` (
  `role_id` int(10) UNSIGNED NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `description`, `created_at`) VALUES
(1, 'Farm Worker', 'General farm worker', '2026-04-15 22:41:34'),
(2, 'Supervisor', 'Supervises workers and field tasks', '2026-04-15 22:41:34'),
(3, 'Payroll Officer', 'Handles payroll processing', '2026-04-15 22:41:34');

-- --------------------------------------------------------

--
-- 表的结构 `roster`
--

CREATE TABLE `roster` (
  `roster_id` int(10) UNSIGNED NOT NULL,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED NOT NULL,
  `work_date` date NOT NULL,
  `shift_type` enum('morning','afternoon') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL
) ;

--
-- 转存表中的数据 `roster`
--

INSERT INTO `roster` (`roster_id`, `staff_id`, `site_id`, `admin_id`, `work_date`, `shift_type`, `start_time`, `end_time`, `created_at`, `created_by`) VALUES
(1, 1, 1, 2, '2024-04-01', 'morning', '08:00:00', '16:00:00', '2026-04-15 22:43:40', 2),
(2, 2, 1, 2, '2024-04-01', 'morning', '08:00:00', '16:00:00', '2026-04-15 22:43:40', 2);

-- --------------------------------------------------------

--
-- 表的结构 `sites`
--

CREATE TABLE `sites` (
  `site_id` int(10) UNSIGNED NOT NULL,
  `site_name` varchar(100) NOT NULL,
  `site_address` varchar(255) DEFAULT NULL,
  `site_contact_number` varchar(30) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `sites`
--

INSERT INTO `sites` (`site_id`, `site_name`, `site_address`, `site_contact_number`, `created_at`) VALUES
(1, 'North Farm', '12 Farm Road, Adelaide', '0412345678', '2026-04-15 22:41:34'),
(2, 'South Farm', '88 Green Valley Rd, Adelaide', '0487654321', '2026-04-15 22:41:34');

-- --------------------------------------------------------

--
-- 表的结构 `staff`
--

CREATE TABLE `staff` (
  `staff_id` int(10) UNSIGNED NOT NULL,
  `staff_number` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `contact_number` varchar(30) NOT NULL,
  `contact_email` varchar(150) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `contract_id` int(10) UNSIGNED DEFAULT NULL,
  `hire_date` date NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `bsb` varchar(7) NOT NULL,
  `account_number` varchar(30) NOT NULL,
  `tfn` varchar(11) DEFAULT NULL,
  `status` enum('Active','Inactive','Suspended','Terminated') NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL
) ;

--
-- 转存表中的数据 `staff`
--

INSERT INTO `staff` (`staff_id`, `staff_number`, `first_name`, `last_name`, `role_id`, `contact_number`, `contact_email`, `address`, `contract_id`, `hire_date`, `bank_name`, `bsb`, `account_number`, `tfn`, `status`, `created_at`, `created_by`) VALUES
(1, 'STF001', 'John', 'Smith', 1, '0411111111', 'john.smith@email.com', '1 King St, Adelaide', 1, '2024-01-15', 'Commonwealth Bank', '123-456', '12345678', '123 456 789', 'Active', '2026-04-15 22:41:50', 1),
(2, 'STF002', 'Mary', 'Brown', 2, '0422222222', 'mary.brown@email.com', '2 Queen St, Adelaide', 2, '2024-03-01', 'ANZ', '234-567', '23456789', '234 567 891', 'Active', '2026-04-15 22:41:50', 1);

-- --------------------------------------------------------

--
-- 视图结构 `monthly_cost_summary`
--
DROP TABLE IF EXISTS `monthly_cost_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `monthly_cost_summary`  AS SELECT date_format(`p`.`pay_date`,'%Y-%m') AS `summary_month`, sum(`p`.`total_pay`) AS `total_pay`, sum(`p`.`net_pay`) AS `total_net_pay`, sum(`p`.`tax_amount`) AS `total_tax`, sum(`p`.`total_pay` * `p`.`super_rate` / 100) AS `total_super` FROM `payslips` AS `p` GROUP BY date_format(`p`.`pay_date`,'%Y-%m') ;

--
-- 转储表的索引
--

--
-- 表的索引 `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `uq_admin_username` (`username`),
  ADD UNIQUE KEY `uq_admin_email` (`email`),
  ADD KEY `idx_admin_site_id` (`site_id`);

--
-- 表的索引 `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD UNIQUE KEY `uq_attendance_roster_id` (`roster_id`),
  ADD KEY `idx_attendance_staff_id` (`staff_id`),
  ADD KEY `idx_attendance_device_id` (`device_id`);

--
-- 表的索引 `attendance_breaks`
--
ALTER TABLE `attendance_breaks`
  ADD PRIMARY KEY (`break_id`),
  ADD KEY `idx_attendance_breaks_attendance_id` (`attendance_id`),
  ADD KEY `idx_attendance_breaks_created_by` (`created_by`);

--
-- 表的索引 `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`audit_log_id`),
  ADD KEY `fk_audit_logs_admin` (`admin_id`);

--
-- 表的索引 `biometric_registrations`
--
ALTER TABLE `biometric_registrations`
  ADD PRIMARY KEY (`biometric_id`),
  ADD UNIQUE KEY `uq_biometric_card_number` (`card_number`),
  ADD KEY `idx_biometric_staff_id` (`staff_id`),
  ADD KEY `idx_biometric_device_id` (`device_id`);

--
-- 表的索引 `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`contract_id`),
  ADD KEY `idx_contracts_staff_id` (`staff_id`),
  ADD KEY `idx_contracts_created_by` (`created_by`);

--
-- 表的索引 `devices`
--
ALTER TABLE `devices`
  ADD PRIMARY KEY (`device_id`),
  ADD UNIQUE KEY `uq_devices_device_number` (`device_number`),
  ADD KEY `idx_devices_site_id` (`site_id`);

--
-- 表的索引 `exceptions`
--
ALTER TABLE `exceptions`
  ADD PRIMARY KEY (`exception_id`),
  ADD KEY `idx_exceptions_attendance_id` (`attendance_id`),
  ADD KEY `idx_exceptions_created_by` (`created_by`);

--
-- 表的索引 `leave_records`
--
ALTER TABLE `leave_records`
  ADD PRIMARY KEY (`leave_id`),
  ADD KEY `idx_leave_records_staff_id` (`staff_id`),
  ADD KEY `idx_leave_records_approved_by` (`approved_by`),
  ADD KEY `fk_leave_records_created_by` (`created_by`);

--
-- 表的索引 `payslips`
--
ALTER TABLE `payslips`
  ADD PRIMARY KEY (`payslip_id`),
  ADD UNIQUE KEY `uq_payslip_staff_period` (`staff_id`,`pay_period_id`),
  ADD KEY `fk_payslips_period` (`pay_period_id`),
  ADD KEY `fk_payslips_generated_by` (`generated_by`);

--
-- 表的索引 `pay_periods`
--
ALTER TABLE `pay_periods`
  ADD PRIMARY KEY (`pay_period_id`),
  ADD KEY `fk_pay_periods_created_by` (`created_by`);

--
-- 表的索引 `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `uq_roles_role_name` (`role_name`);

--
-- 表的索引 `roster`
--
ALTER TABLE `roster`
  ADD PRIMARY KEY (`roster_id`),
  ADD UNIQUE KEY `uq_roster_staff_work_date` (`staff_id`,`work_date`),
  ADD KEY `idx_roster_site_id` (`site_id`),
  ADD KEY `idx_roster_admin_id` (`admin_id`),
  ADD KEY `idx_roster_created_by` (`created_by`);

--
-- 表的索引 `sites`
--
ALTER TABLE `sites`
  ADD PRIMARY KEY (`site_id`);

--
-- 表的索引 `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`staff_id`),
  ADD UNIQUE KEY `uq_staff_staff_number` (`staff_number`),
  ADD KEY `idx_staff_role_id` (`role_id`),
  ADD KEY `idx_staff_created_by` (`created_by`),
  ADD KEY `fk_staff_contract` (`contract_id`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- 使用表AUTO_INCREMENT `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `attendance_breaks`
--
ALTER TABLE `attendance_breaks`
  MODIFY `break_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `audit_log_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `biometric_registrations`
--
ALTER TABLE `biometric_registrations`
  MODIFY `biometric_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- 使用表AUTO_INCREMENT `contracts`
--
ALTER TABLE `contracts`
  MODIFY `contract_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `devices`
--
ALTER TABLE `devices`
  MODIFY `device_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- 使用表AUTO_INCREMENT `exceptions`
--
ALTER TABLE `exceptions`
  MODIFY `exception_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `leave_records`
--
ALTER TABLE `leave_records`
  MODIFY `leave_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `payslips`
--
ALTER TABLE `payslips`
  MODIFY `payslip_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `pay_periods`
--
ALTER TABLE `pay_periods`
  MODIFY `pay_period_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- 使用表AUTO_INCREMENT `roster`
--
ALTER TABLE `roster`
  MODIFY `roster_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `sites`
--
ALTER TABLE `sites`
  MODIFY `site_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- 使用表AUTO_INCREMENT `staff`
--
ALTER TABLE `staff`
  MODIFY `staff_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 限制导出的表
--

--
-- 限制表 `admin`
--
ALTER TABLE `admin`
  ADD CONSTRAINT `fk_admin_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`site_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- 限制表 `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_attendance_device` FOREIGN KEY (`device_id`) REFERENCES `devices` (`device_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_attendance_roster` FOREIGN KEY (`roster_id`) REFERENCES `roster` (`roster_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_attendance_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON UPDATE CASCADE;

--
-- 限制表 `attendance_breaks`
--
ALTER TABLE `attendance_breaks`
  ADD CONSTRAINT `fk_attendance_breaks_attendance` FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`attendance_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_attendance_breaks_created_by` FOREIGN KEY (`created_by`) REFERENCES `admin` (`admin_id`) ON UPDATE CASCADE;

--
-- 限制表 `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_logs_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`admin_id`) ON UPDATE CASCADE;

--
-- 限制表 `biometric_registrations`
--
ALTER TABLE `biometric_registrations`
  ADD CONSTRAINT `fk_biometric_device` FOREIGN KEY (`device_id`) REFERENCES `devices` (`device_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_biometric_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON UPDATE CASCADE;

--
-- 限制表 `contracts`
--
ALTER TABLE `contracts`
  ADD CONSTRAINT `fk_contracts_created_by` FOREIGN KEY (`created_by`) REFERENCES `admin` (`admin_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_contracts_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON UPDATE CASCADE;

--
-- 限制表 `devices`
--
ALTER TABLE `devices`
  ADD CONSTRAINT `fk_devices_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`site_id`) ON UPDATE CASCADE;

--
-- 限制表 `exceptions`
--
ALTER TABLE `exceptions`
  ADD CONSTRAINT `fk_exceptions_attendance` FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`attendance_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_exceptions_created_by` FOREIGN KEY (`created_by`) REFERENCES `admin` (`admin_id`) ON UPDATE CASCADE;

--
-- 限制表 `leave_records`
--
ALTER TABLE `leave_records`
  ADD CONSTRAINT `fk_leave_records_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `admin` (`admin_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_leave_records_created_by` FOREIGN KEY (`created_by`) REFERENCES `admin` (`admin_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_leave_records_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON UPDATE CASCADE;

--
-- 限制表 `payslips`
--
ALTER TABLE `payslips`
  ADD CONSTRAINT `fk_payslips_generated_by` FOREIGN KEY (`generated_by`) REFERENCES `admin` (`admin_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payslips_period` FOREIGN KEY (`pay_period_id`) REFERENCES `pay_periods` (`pay_period_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payslips_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON UPDATE CASCADE;

--
-- 限制表 `pay_periods`
--
ALTER TABLE `pay_periods`
  ADD CONSTRAINT `fk_pay_periods_created_by` FOREIGN KEY (`created_by`) REFERENCES `admin` (`admin_id`) ON UPDATE CASCADE;

--
-- 限制表 `roster`
--
ALTER TABLE `roster`
  ADD CONSTRAINT `fk_roster_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`admin_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_roster_created_by` FOREIGN KEY (`created_by`) REFERENCES `admin` (`admin_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_roster_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`site_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_roster_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON UPDATE CASCADE;

--
-- 限制表 `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `fk_staff_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`contract_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_staff_created_by` FOREIGN KEY (`created_by`) REFERENCES `admin` (`admin_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_staff_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
