<?php
require_once 'config/database.php';

class Doctor {
    private $conn;
    private $table_name = "doctors";
    
    public $id;
    public $user_id;
    public $full_name;
    public $license_number;
    public $specialization;
    public $phone;
    public $address;
    public $hospital_clinic;
    public $experience_years;
    public $education;
    public $is_verified;
    public $created_at;
    public $updated_at;
    
    public function __construct() {
        $this->conn = getDBConnection();
    }
    
    // Create new doctor
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (user_id, full_name, license_number, specialization, phone, address, 
                   hospital_clinic, experience_years, education, is_verified) 
                  VALUES (:user_id, :full_name, :license_number, :specialization, :phone, 
                          :address, :hospital_clinic, :experience_years, :education, :is_verified)";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitize
        $this->full_name = htmlspecialchars(strip_tags($this->full_name));
        $this->license_number = htmlspecialchars(strip_tags($this->license_number));
        $this->specialization = htmlspecialchars(strip_tags($this->specialization));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->address = htmlspecialchars(strip_tags($this->address));
        $this->hospital_clinic = htmlspecialchars(strip_tags($this->hospital_clinic));
        $this->education = htmlspecialchars(strip_tags($this->education));
        
        // Bind values
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":full_name", $this->full_name);
        $stmt->bindParam(":license_number", $this->license_number);
        $stmt->bindParam(":specialization", $this->specialization);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":address", $this->address);
        $stmt->bindParam(":hospital_clinic", $this->hospital_clinic);
        $stmt->bindParam(":experience_years", $this->experience_years);
        $stmt->bindParam(":education", $this->education);
        $stmt->bindParam(":is_verified", $this->is_verified);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    // Get doctor by user ID
    public function getByUserId($user_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch();
            $this->id = $row['id'];
            $this->user_id = $row['user_id'];
            $this->full_name = $row['full_name'];
            $this->license_number = $row['license_number'];
            $this->specialization = $row['specialization'];
            $this->phone = $row['phone'];
            $this->address = $row['address'];
            $this->hospital_clinic = $row['hospital_clinic'];
            $this->experience_years = $row['experience_years'];
            $this->education = $row['education'];
            $this->is_verified = $row['is_verified'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }
        
        return false;
    }
    
    // Check if license number exists
    public function licenseExists($license_number, $exclude_id = null) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE license_number = :license_number";
        
        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":license_number", $license_number);
        
        if ($exclude_id) {
            $stmt->bindParam(":exclude_id", $exclude_id);
        }
        
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }
}
?>
