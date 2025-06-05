<?php
// PharmaSys - Pharmacy Management System
session_start();

// Initialize sample data if not exists
if (!isset($_SESSION['medications'])) {
    $_SESSION['medications'] = [
        [
            'id' => 1,
            'name' => 'Paracetamol',
            'description' => 'Pain reliever',
            'unit' => 'tablet',
            'price' => 5000,
            'stock' => 100
        ],
        [
            'id' => 2,
            'name' => 'Amoxicillin',
            'description' => 'Antibiotic',
            'unit' => 'capsule',
            'price' => 15000,
            'stock' => 50
        ],
        [
            'id' => 3,
            'name' => 'Loratadine',
            'description' => 'Antihistamine',
            'unit' => 'tablet',
            'price' => 8000,
            'stock' => 75
        ]
    ];
}

if (!isset($_SESSION['orders'])) {
    $_SESSION['orders'] = [
        [
            'id' => 1,
            'doctorName' => 'Dr. Budi Santoso',
            'patientName' => 'Ahmad Rizki',
            'status' => 'new',
            'date' => '2023-06-01',
            'items' => [
                ['medicationId' => 1, 'quantity' => 10],
                ['medicationId' => 3, 'quantity' => 5]
            ]
        ],
        [
            'id' => 2,
            'doctorName' => 'Dr. Siti Rahayu',
            'patientName' => 'Maya Putri',
            'status' => 'preparing',
            'date' => '2023-06-01',
            'items' => [
                ['medicationId' => 2, 'quantity' => 15]
            ]
        ],
        [
            'id' => 3,
            'doctorName' => 'Dr. Andi Wijaya',
            'patientName' => 'Budi Prakoso',
            'status' => 'completed',
            'date' => '2023-05-30',
            'items' => [
                ['medicationId' => 1, 'quantity' => 20],
                ['medicationId' => 2, 'quantity' => 10]
            ]
        ]
    ];
}

if (!isset($_SESSION['pharmacy'])) {
    $_SESSION['pharmacy'] = [
        'name' => 'Apotek Sehat',
        'address' => 'Jl. Kesehatan No. 123, Jakarta',
        'phone' => '021-12345678',
        'email' => 'info@apoteksehat.com',
        'operationalHours' => '08:00 - 21:00',
        'description' => 'Apotek Sehat adalah apotek terpercaya yang menyediakan berbagai macam obat dengan kualitas terbaik dan harga terjangkau.'
    ];
}

// Redirect to login page
header("Location: login.php");
exit();
?>
