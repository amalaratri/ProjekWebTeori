-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Waktu pembuatan: 13 Jun 2025 pada 07.19
-- Versi server: 8.0.30
-- Versi PHP: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pharmasys`
--

DELIMITER $$
--
-- Prosedur
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `GenerateOrderNumber` (OUT `p_order_number` VARCHAR(50))   BEGIN
    DECLARE v_count INT;
    DECLARE v_date_part VARCHAR(10);
    
    SET v_date_part = DATE_FORMAT(CURDATE(), '%Y%m%d');
    
    SELECT COUNT(*) + 1 INTO v_count
    FROM orders 
    WHERE DATE(order_date) = CURDATE();
    
    SET p_order_number = CONCAT('ORD-', v_date_part, '-', LPAD(v_count, 3, '0'));
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdateStockAfterOrder` (IN `p_order_id` INT)   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_pharmacy_id INT;
    DECLARE v_medication_id INT;
    DECLARE v_quantity INT;
    
    DECLARE cur CURSOR FOR 
        SELECT o.pharmacy_id, oi.medication_id, oi.quantity
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        WHERE o.id = p_order_id;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO v_pharmacy_id, v_medication_id, v_quantity;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Update inventory
        UPDATE pharmacy_inventory 
        SET stock_quantity = stock_quantity - v_quantity,
            updated_at = CURRENT_TIMESTAMP
        WHERE pharmacy_id = v_pharmacy_id 
          AND medication_id = v_medication_id;
        
        -- Record stock movement
        INSERT INTO stock_movements (
            pharmacy_id, medication_id, movement_type, quantity, 
            reference_type, reference_id, created_by
        ) VALUES (
            v_pharmacy_id, v_medication_id, 'out', v_quantity,
            'order', p_order_id, 1
        );
        
    END LOOP;
    
    CLOSE cur;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `doctors`
--

CREATE TABLE `doctors` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `license_number` varchar(100) NOT NULL,
  `specialization` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `hospital_clinic` varchar(255) NOT NULL,
  `experience_years` int NOT NULL,
  `education` varchar(255) NOT NULL,
  `is_verified` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `doctors`
--

INSERT INTO `doctors` (`id`, `user_id`, `full_name`, `license_number`, `specialization`, `phone`, `address`, `hospital_clinic`, `experience_years`, `education`, `is_verified`, `created_at`, `updated_at`) VALUES
(1, 2, 'Dr. Budi Prakoso, Sp.PD', 'DOC-001-2024', 'Internal Medicine', '081234567890', 'Jl. Dokter No. 456, Jakarta Selatan, DKI Jakarta', 'RS Cipto Mangunkusumo', 10, 'S1 FK UI, Sp.PD RSCM', 1, '2025-06-02 11:26:23', '2025-06-02 11:26:23'),
(2, 4, 'Dr. Siti Rahayu, Sp.A', 'DOC-002-2024', 'Pediatrics', '081987654321', 'Jl. Anak Sehat No. 789, Jakarta Timur, DKI Jakarta', 'RS Anak dan Bunda Harapan Kita', 8, 'S1 FK UGM, Sp.A RSAB Harapan Kita', 1, '2025-06-02 11:26:23', '2025-06-02 11:26:23');

-- --------------------------------------------------------

--
-- Struktur dari tabel `medications`
--

CREATE TABLE `medications` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `generic_name` varchar(255) DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `description` text,
  `dosage_form` varchar(100) DEFAULT NULL,
  `strength` varchar(100) DEFAULT NULL,
  `manufacturer` varchar(255) DEFAULT NULL,
  `requires_prescription` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `medications`
--

INSERT INTO `medications` (`id`, `name`, `generic_name`, `category_id`, `description`, `dosage_form`, `strength`, `manufacturer`, `requires_prescription`, `created_at`, `updated_at`) VALUES
(1, 'Paracetamol', 'Acetaminophen', 1, 'Pain reliever and fever reducer', 'Tablet', '500mg', 'Kimia Farma', 0, '2025-06-02 11:26:22', '2025-06-02 11:26:22'),
(2, 'Amoxicillin', 'Amoxicillin', 2, 'Antibiotic for bacterial infections', 'Capsule', '500mg', 'Dexa Medica', 1, '2025-06-02 11:26:22', '2025-06-02 11:26:22'),
(3, 'Loratadine', 'Loratadine', 3, 'Antihistamine for allergies', 'Tablet', '10mg', 'Kalbe Farma', 0, '2025-06-02 11:26:22', '2025-06-02 11:26:22'),
(4, 'Amlodipine', 'Amlodipine', 4, 'Calcium channel blocker for hypertension', 'Tablet', '5mg', 'Novartis', 1, '2025-06-02 11:26:22', '2025-06-02 11:26:22'),
(5, 'Omeprazole', 'Omeprazole', 5, 'Proton pump inhibitor for acid reflux', 'Capsule', '20mg', 'AstraZeneca', 1, '2025-06-02 11:26:22', '2025-06-02 11:26:22'),
(6, 'Salbutamol', 'Salbutamol', 6, 'Bronchodilator for asthma', 'Inhaler', '100mcg', 'GSK', 1, '2025-06-02 11:26:22', '2025-06-02 11:26:22'),
(7, 'Hydrocortisone', 'Hydrocortisone', 7, 'Topical corticosteroid for skin inflammation', 'Cream', '1%', 'Johnson & Johnson', 0, '2025-06-02 11:26:22', '2025-06-02 11:26:22'),
(8, 'Diazepam', 'Diazepam', 8, 'Benzodiazepine for anxiety and seizures', 'Tablet', '5mg', 'Roche', 1, '2025-06-02 11:26:22', '2025-06-02 11:26:22'),
(9, 'Metformin', 'Metformin', 9, 'Antidiabetic medication', 'Tablet', '500mg', 'Merck', 1, '2025-06-02 11:26:22', '2025-06-02 11:26:22'),
(10, 'Vitamin C', 'Ascorbic Acid', 10, 'Vitamin C supplement', 'Tablet', '1000mg', 'Blackmores', 0, '2025-06-02 11:26:22', '2025-06-02 11:26:22');

-- --------------------------------------------------------

--
-- Struktur dari tabel `medication_categories`
--

CREATE TABLE `medication_categories` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `medication_categories`
--

INSERT INTO `medication_categories` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Analgesics', 'Pain relievers and anti-inflammatory medications', '2025-06-02 11:26:22'),
(2, 'Antibiotics', 'Medications to treat bacterial infections', '2025-06-02 11:26:22'),
(3, 'Antihistamines', 'Medications to treat allergic reactions', '2025-06-02 11:26:22'),
(4, 'Cardiovascular', 'Medications for heart and blood vessel conditions', '2025-06-02 11:26:22'),
(5, 'Gastrointestinal', 'Medications for digestive system disorders', '2025-06-02 11:26:22'),
(6, 'Respiratory', 'Medications for breathing and lung conditions', '2025-06-02 11:26:22'),
(7, 'Dermatological', 'Medications for skin conditions', '2025-06-02 11:26:22'),
(8, 'Neurological', 'Medications for nervous system disorders', '2025-06-02 11:26:22'),
(9, 'Endocrine', 'Medications for hormonal disorders', '2025-06-02 11:26:22'),
(10, 'Vitamins & Supplements', 'Nutritional supplements and vitamins', '2025-06-02 11:26:22');

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifications`
--

CREATE TABLE `notifications` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT '0',
  `action_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `orders`
--

CREATE TABLE `orders` (
  `id` int NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `doctor_id` int NOT NULL,
  `pharmacy_id` int NOT NULL,
  `patient_name` varchar(255) NOT NULL,
  `patient_age` int DEFAULT NULL,
  `patient_gender` enum('male','female') DEFAULT NULL,
  `patient_phone` varchar(20) DEFAULT NULL,
  `diagnosis` text,
  `notes` text,
  `status` enum('pending','confirmed','preparing','ready','completed','cancelled') DEFAULT 'pending',
  `total_amount` decimal(10,2) DEFAULT '0.00',
  `order_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `doctor_id`, `pharmacy_id`, `patient_name`, `patient_age`, `patient_gender`, `patient_phone`, `diagnosis`, `notes`, `status`, `total_amount`, `order_date`, `confirmed_at`, `completed_at`, `created_at`, `updated_at`) VALUES
(1, 'ORD-2024-001', 1, 1, 'Ahmad Rizki', 35, 'male', '081234567890', 'Hypertension', 'Take medication after meals', 'completed', 37000.00, '2025-06-02 11:26:23', NULL, NULL, '2025-06-02 11:26:23', '2025-06-02 11:26:23'),
(2, 'ORD-2024-002', 2, 1, 'Maya Putri', 28, 'female', '081987654321', 'Upper respiratory tract infection', 'Complete the antibiotic course', 'preparing', 30000.00, '2025-06-02 11:26:23', NULL, NULL, '2025-06-02 11:26:23', '2025-06-02 11:26:23'),
(3, 'ORD-2024-003', 1, 2, 'Budi Prakoso', 45, 'male', '081122334455', 'Type 2 Diabetes Mellitus', 'Monitor blood sugar regularly', 'pending', 23500.00, '2025-06-02 11:26:23', NULL, NULL, '2025-06-02 11:26:23', '2025-06-02 11:26:23');

--
-- Trigger `orders`
--
DELIMITER $$
CREATE TRIGGER `tr_log_order_status_change` AFTER UPDATE ON `orders` FOR EACH ROW BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO order_status_history (
            order_id, old_status, new_status, changed_by, notes
        ) VALUES (
            NEW.id, OLD.status, NEW.status, 1, 
            CONCAT('Status changed from ', OLD.status, ' to ', NEW.status)
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `order_items`
--

CREATE TABLE `order_items` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `medication_id` int NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `dosage_instructions` text,
  `duration_days` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `medication_id`, `quantity`, `unit_price`, `total_price`, `dosage_instructions`, `duration_days`, `created_at`) VALUES
(1, 1, 4, 2, 12000.00, 24000.00, '1 tablet once daily in the morning', 30, '2025-06-02 11:26:23'),
(2, 1, 1, 10, 5000.00, 13000.00, '1 tablet every 6 hours as needed for pain', 5, '2025-06-02 11:26:23'),
(3, 2, 2, 15, 15000.00, 30000.00, '1 capsule every 8 hours for 7 days', 7, '2025-06-02 11:26:23'),
(4, 3, 9, 30, 8500.00, 23500.00, '1 tablet twice daily before meals', 30, '2025-06-02 11:26:23');

--
-- Trigger `order_items`
--
DELIMITER $$
CREATE TRIGGER `tr_update_order_total` AFTER INSERT ON `order_items` FOR EACH ROW BEGIN
    UPDATE orders 
    SET total_amount = (
        SELECT SUM(total_price) 
        FROM order_items 
        WHERE order_id = NEW.order_id
    )
    WHERE id = NEW.order_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `order_status_history`
--

CREATE TABLE `order_status_history` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `changed_by` int NOT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pharmacies`
--

CREATE TABLE `pharmacies` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `pharmacy_name` varchar(255) NOT NULL,
  `owner_name` varchar(255) NOT NULL,
  `license_number` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `operational_hours` varchar(100) NOT NULL,
  `description` text,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `pharmacies`
--

INSERT INTO `pharmacies` (`id`, `user_id`, `pharmacy_name`, `owner_name`, `license_number`, `phone`, `address`, `operational_hours`, `description`, `latitude`, `longitude`, `is_verified`, `created_at`, `updated_at`) VALUES
(1, 1, 'Apotek Sehat Sentosa', 'Budi Santoso', 'APT-001-2024', '021-12345678', 'Jl. Kesehatan No. 123, Jakarta Pusat, DKI Jakarta', '08:00 - 21:00', 'Apotek terpercaya dengan pelayanan 24/7 dan stok obat lengkap', NULL, NULL, 1, '2025-06-02 11:26:22', '2025-06-02 11:26:22'),
(2, 3, 'Kimia Farma Cikini', 'Sari Dewi', 'APT-002-2024', '021-87654321', 'Jl. Cikini Raya No. 45, Jakarta Pusat, DKI Jakarta', '07:00 - 22:00', 'Apotek cabang Kimia Farma dengan layanan konsultasi gratis', NULL, NULL, 1, '2025-06-02 11:26:22', '2025-06-02 11:26:22');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pharmacy_inventory`
--

CREATE TABLE `pharmacy_inventory` (
  `id` int NOT NULL,
  `pharmacy_id` int NOT NULL,
  `medication_id` int NOT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `stock_quantity` int NOT NULL DEFAULT '0',
  `minimum_stock` int DEFAULT '10',
  `unit` varchar(50) NOT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `last_restocked` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `pharmacy_inventory`
--

INSERT INTO `pharmacy_inventory` (`id`, `pharmacy_id`, `medication_id`, `batch_number`, `expiry_date`, `unit_price`, `stock_quantity`, `minimum_stock`, `unit`, `supplier`, `last_restocked`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'PCM240101', '2025-12-31', 5000.00, 100, 20, 'tablet', 'PT Kimia Farma', NULL, '2025-06-02 11:26:23', '2025-06-02 11:26:23'),
(2, 1, 2, 'AMX240102', '2025-06-30', 15000.00, 50, 10, 'capsule', 'PT Dexa Medica', NULL, '2025-06-02 11:26:23', '2025-06-02 11:26:23'),
(3, 1, 3, 'LOR240103', '2025-09-15', 8000.00, 75, 15, 'tablet', 'PT Kalbe Farma', NULL, '2025-06-02 11:26:23', '2025-06-02 11:26:23'),
(4, 1, 4, 'AML240104', '2025-11-20', 12000.00, 30, 10, 'tablet', 'PT Novartis', NULL, '2025-06-02 11:26:23', '2025-06-02 11:26:23'),
(5, 1, 5, 'OME240105', '2025-08-10', 25000.00, 40, 10, 'capsule', 'PT AstraZeneca', NULL, '2025-06-02 11:26:23', '2025-06-02 11:26:23'),
(6, 2, 1, 'PCM240201', '2025-12-31', 4500.00, 150, 25, 'tablet', 'PT Kimia Farma', NULL, '2025-06-02 11:26:23', '2025-06-02 11:26:23'),
(7, 2, 2, 'AMX240202', '2025-07-15', 14000.00, 60, 15, 'capsule', 'PT Dexa Medica', NULL, '2025-06-02 11:26:23', '2025-06-02 11:26:23'),
(8, 2, 6, 'SAL240203', '2025-10-30', 45000.00, 25, 5, 'inhaler', 'PT GSK', NULL, '2025-06-02 11:26:23', '2025-06-02 11:26:23'),
(9, 2, 9, 'MET240204', '2025-12-25', 8500.00, 80, 20, 'tablet', 'PT Merck', NULL, '2025-06-02 11:26:23', '2025-06-02 11:26:23'),
(10, 2, 10, 'VTC240205', '2026-01-15', 15000.00, 100, 25, 'tablet', 'PT Blackmores', NULL, '2025-06-02 11:26:23', '2025-06-02 11:26:23');

-- --------------------------------------------------------

--
-- Struktur dari tabel `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int NOT NULL,
  `pharmacy_id` int NOT NULL,
  `medication_id` int NOT NULL,
  `movement_type` enum('in','out','adjustment','expired') NOT NULL,
  `quantity` int NOT NULL,
  `reference_type` enum('purchase','sale','adjustment','expiry','order') NOT NULL,
  `reference_id` int DEFAULT NULL,
  `notes` text,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `pharmacy_id`, `medication_id`, `movement_type`, `quantity`, `reference_type`, `reference_id`, `notes`, `created_by`, `created_at`) VALUES
(1, 1, 4, 'out', 2, 'order', 1, 'Dispensed for order ORD-2024-001', 1, '2025-06-02 11:26:23'),
(2, 1, 1, 'out', 10, 'order', 1, 'Dispensed for order ORD-2024-001', 1, '2025-06-02 11:26:23'),
(3, 1, 2, 'out', 15, 'order', 2, 'Dispensed for order ORD-2024-002', 1, '2025-06-02 11:26:23');

-- --------------------------------------------------------

--
-- Struktur dari tabel `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `description` text,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_by`, `updated_at`) VALUES
(1, 'system_name', 'PharmaSys', 'System name displayed in the application', NULL, '2025-06-02 11:26:23'),
(2, 'currency', 'IDR', 'Default currency for pricing', NULL, '2025-06-02 11:26:23'),
(3, 'timezone', 'Asia/Jakarta', 'Default timezone for the system', NULL, '2025-06-02 11:26:23'),
(4, 'low_stock_threshold', '10', 'Default minimum stock threshold', NULL, '2025-06-02 11:26:23'),
(5, 'order_expiry_hours', '24', 'Hours after which pending orders expire', NULL, '2025-06-02 11:26:23'),
(6, 'notification_email', 'admin@pharmasys.com', 'System notification email address', NULL, '2025-06-02 11:26:23');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('pharmacy','doctor') NOT NULL,
  `status` enum('active','inactive','pending') DEFAULT 'active',
  `email_verified` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `user_type`, `status`, `email_verified`, `created_at`, `updated_at`) VALUES
(1, 'apotek.sehat@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pharmacy', 'active', 1, '2025-06-02 11:26:22', '2025-06-02 11:26:22'),
(2, 'dr.budi@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'active', 1, '2025-06-02 11:26:22', '2025-06-02 11:26:22'),
(3, 'apotek.kimia@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pharmacy', 'active', 1, '2025-06-02 11:26:22', '2025-06-02 11:26:22'),
(4, 'dr.siti@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'active', 1, '2025-06-02 11:26:22', '2025-06-02 11:26:22');

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `v_low_stock_alerts`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `v_low_stock_alerts` (
`pharmacy_id` int
,`pharmacy_name` varchar(255)
,`medication_id` int
,`medication_name` varchar(255)
,`stock_quantity` int
,`minimum_stock` int
,`unit` varchar(50)
,`days_to_expiry` int
);

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `v_order_details`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `v_order_details` (
`id` int
,`order_number` varchar(50)
,`doctor_id` int
,`doctor_name` varchar(255)
,`specialization` varchar(100)
,`pharmacy_id` int
,`pharmacy_name` varchar(255)
,`patient_name` varchar(255)
,`patient_age` int
,`patient_gender` enum('male','female')
,`patient_phone` varchar(20)
,`diagnosis` text
,`notes` text
,`status` enum('pending','confirmed','preparing','ready','completed','cancelled')
,`total_amount` decimal(10,2)
,`order_date` timestamp
,`confirmed_at` timestamp
,`completed_at` timestamp
,`item_count` bigint
);

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `v_pharmacy_inventory`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `v_pharmacy_inventory` (
`id` int
,`pharmacy_id` int
,`pharmacy_name` varchar(255)
,`medication_id` int
,`medication_name` varchar(255)
,`generic_name` varchar(255)
,`category_name` varchar(100)
,`batch_number` varchar(100)
,`expiry_date` date
,`unit_price` decimal(10,2)
,`stock_quantity` int
,`minimum_stock` int
,`unit` varchar(50)
,`supplier` varchar(255)
,`stock_status` varchar(13)
,`last_restocked` timestamp
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Struktur untuk view `v_low_stock_alerts`
--
DROP TABLE IF EXISTS `v_low_stock_alerts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_low_stock_alerts`  AS SELECT `pi`.`pharmacy_id` AS `pharmacy_id`, `p`.`pharmacy_name` AS `pharmacy_name`, `pi`.`medication_id` AS `medication_id`, `m`.`name` AS `medication_name`, `pi`.`stock_quantity` AS `stock_quantity`, `pi`.`minimum_stock` AS `minimum_stock`, `pi`.`unit` AS `unit`, (to_days(`pi`.`expiry_date`) - to_days(curdate())) AS `days_to_expiry` FROM ((`pharmacy_inventory` `pi` join `pharmacies` `p` on((`pi`.`pharmacy_id` = `p`.`id`))) join `medications` `m` on((`pi`.`medication_id` = `m`.`id`))) WHERE ((`pi`.`stock_quantity` <= `pi`.`minimum_stock`) OR (`pi`.`expiry_date` <= (curdate() + interval 3 month))) ;

-- --------------------------------------------------------

--
-- Struktur untuk view `v_order_details`
--
DROP TABLE IF EXISTS `v_order_details`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_order_details`  AS SELECT `o`.`id` AS `id`, `o`.`order_number` AS `order_number`, `o`.`doctor_id` AS `doctor_id`, `d`.`full_name` AS `doctor_name`, `d`.`specialization` AS `specialization`, `o`.`pharmacy_id` AS `pharmacy_id`, `p`.`pharmacy_name` AS `pharmacy_name`, `o`.`patient_name` AS `patient_name`, `o`.`patient_age` AS `patient_age`, `o`.`patient_gender` AS `patient_gender`, `o`.`patient_phone` AS `patient_phone`, `o`.`diagnosis` AS `diagnosis`, `o`.`notes` AS `notes`, `o`.`status` AS `status`, `o`.`total_amount` AS `total_amount`, `o`.`order_date` AS `order_date`, `o`.`confirmed_at` AS `confirmed_at`, `o`.`completed_at` AS `completed_at`, count(`oi`.`id`) AS `item_count` FROM (((`orders` `o` join `doctors` `d` on((`o`.`doctor_id` = `d`.`id`))) join `pharmacies` `p` on((`o`.`pharmacy_id` = `p`.`id`))) left join `order_items` `oi` on((`o`.`id` = `oi`.`order_id`))) GROUP BY `o`.`id` ;

-- --------------------------------------------------------

--
-- Struktur untuk view `v_pharmacy_inventory`
--
DROP TABLE IF EXISTS `v_pharmacy_inventory`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_pharmacy_inventory`  AS SELECT `pi`.`id` AS `id`, `pi`.`pharmacy_id` AS `pharmacy_id`, `p`.`pharmacy_name` AS `pharmacy_name`, `pi`.`medication_id` AS `medication_id`, `m`.`name` AS `medication_name`, `m`.`generic_name` AS `generic_name`, `mc`.`name` AS `category_name`, `pi`.`batch_number` AS `batch_number`, `pi`.`expiry_date` AS `expiry_date`, `pi`.`unit_price` AS `unit_price`, `pi`.`stock_quantity` AS `stock_quantity`, `pi`.`minimum_stock` AS `minimum_stock`, `pi`.`unit` AS `unit`, `pi`.`supplier` AS `supplier`, (case when (`pi`.`stock_quantity` <= `pi`.`minimum_stock`) then 'Low Stock' when (`pi`.`expiry_date` <= (curdate() + interval 3 month)) then 'Expiring Soon' else 'Normal' end) AS `stock_status`, `pi`.`last_restocked` AS `last_restocked`, `pi`.`updated_at` AS `updated_at` FROM (((`pharmacy_inventory` `pi` join `pharmacies` `p` on((`pi`.`pharmacy_id` = `p`.`id`))) join `medications` `m` on((`pi`.`medication_id` = `m`.`id`))) left join `medication_categories` `mc` on((`m`.`category_id` = `mc`.`id`))) ;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `license_number` (`license_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_doctor_name` (`full_name`),
  ADD KEY `idx_license` (`license_number`),
  ADD KEY `idx_specialization` (`specialization`),
  ADD KEY `idx_verified` (`is_verified`);

--
-- Indeks untuk tabel `medications`
--
ALTER TABLE `medications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_medication_name` (`name`),
  ADD KEY `idx_generic_name` (`generic_name`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_medications_name_category` (`name`,`category_id`);

--
-- Indeks untuk tabel `medication_categories`
--
ALTER TABLE `medication_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category_name` (`name`);

--
-- Indeks untuk tabel `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_notifications` (`user_id`,`is_read`,`created_at`);

--
-- Indeks untuk tabel `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `idx_order_number` (`order_number`),
  ADD KEY `idx_doctor_orders` (`doctor_id`,`order_date`),
  ADD KEY `idx_pharmacy_orders` (`pharmacy_id`,`order_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_patient` (`patient_name`),
  ADD KEY `idx_orders_date_status` (`order_date`,`status`);

--
-- Indeks untuk tabel `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_items` (`order_id`),
  ADD KEY `idx_medication_orders` (`medication_id`);

--
-- Indeks untuk tabel `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `changed_by` (`changed_by`),
  ADD KEY `idx_order_history` (`order_id`,`created_at`);

--
-- Indeks untuk tabel `pharmacies`
--
ALTER TABLE `pharmacies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `license_number` (`license_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_pharmacy_name` (`pharmacy_name`),
  ADD KEY `idx_license` (`license_number`),
  ADD KEY `idx_verified` (`is_verified`);

--
-- Indeks untuk tabel `pharmacy_inventory`
--
ALTER TABLE `pharmacy_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_pharmacy_medication` (`pharmacy_id`,`medication_id`),
  ADD KEY `medication_id` (`medication_id`),
  ADD KEY `idx_pharmacy_stock` (`pharmacy_id`,`stock_quantity`),
  ADD KEY `idx_expiry` (`expiry_date`),
  ADD KEY `idx_low_stock` (`stock_quantity`,`minimum_stock`),
  ADD KEY `idx_inventory_pharmacy_stock` (`pharmacy_id`,`stock_quantity`);

--
-- Indeks untuk tabel `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_pharmacy_movements` (`pharmacy_id`,`created_at`),
  ADD KEY `idx_medication_movements` (`medication_id`,`created_at`),
  ADD KEY `idx_movement_type` (`movement_type`),
  ADD KEY `idx_stock_movements_date` (`created_at`);

--
-- Indeks untuk tabel `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_setting_key` (`setting_key`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_user_type` (`user_type`),
  ADD KEY `idx_status` (`status`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `doctors`
--
ALTER TABLE `doctors`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `medications`
--
ALTER TABLE `medications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `medication_categories`
--
ALTER TABLE `medication_categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pharmacies`
--
ALTER TABLE `pharmacies`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `pharmacy_inventory`
--
ALTER TABLE `pharmacy_inventory`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `doctors`
--
ALTER TABLE `doctors`
  ADD CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `medications`
--
ALTER TABLE `medications`
  ADD CONSTRAINT `medications_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `medication_categories` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`medication_id`) REFERENCES `medications` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD CONSTRAINT `order_status_history_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_status_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pharmacies`
--
ALTER TABLE `pharmacies`
  ADD CONSTRAINT `pharmacies_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pharmacy_inventory`
--
ALTER TABLE `pharmacy_inventory`
  ADD CONSTRAINT `pharmacy_inventory_ibfk_1` FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pharmacy_inventory_ibfk_2` FOREIGN KEY (`medication_id`) REFERENCES `medications` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`medication_id`) REFERENCES `medications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_movements_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
