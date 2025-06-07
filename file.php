

// Security functions
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generateToken() {
    return bin2hex(random_bytes(32));
}

function validateWalletAddress($address) {
    return preg_match('/^0x[a-fA-F0-9]{40}$/', $address);
}

function cors() {
    // Allow CORS for API requests
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        exit(0);
    }
}

// Response helper
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// =======================
// User Management Class
// =======================

class UserManager {
    private $conn;
    private $table = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function createUser($wallet_address, $email = null) {
        $query = "INSERT INTO " . $this->table . " 
                  (wallet_address, email, created_at, updated_at) 
                  VALUES (:wallet_address, :email, NOW(), NOW())";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":wallet_address", $wallet_address);
        $stmt->bindParam(":email", $email);
        
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function getUserByWallet($wallet_address) {
        $query = "SELECT * FROM " . $this->table . " WHERE wallet_address = :wallet_address";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":wallet_address", $wallet_address);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateBalance($wallet_address, $balance) {
        $query = "UPDATE " . $this->table . " 
                  SET bank_balance = :balance, updated_at = NOW() 
                  WHERE wallet_address = :wallet_address";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":balance", $balance);
        $stmt->bindParam(":wallet_address", $wallet_address);
        
        return $stmt->execute();
    }
}

// =======================
// Transaction Manager Class
// =======================

class TransactionManager {
    private $conn;
    private $table = "transactions";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function createTransaction($data) {
        $query = "INSERT INTO " . $this->table . " 
                  (user_id, transaction_type, amount, recipient_address, 
                   transaction_hash, status, details, created_at) 
                  VALUES (:user_id, :type, :amount, :recipient, :hash, :status, :details, NOW())";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $data['user_id']);
        $stmt->bindParam(":type", $data['type']);
        $stmt->bindParam(":amount", $data['amount']);
        $stmt->bindParam(":recipient", $data['recipient']);
        $stmt->bindParam(":hash", $data['hash']);
        $stmt->bindParam(":status", $data['status']);
        $stmt->bindParam(":details", $data['details']);
        
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function getUserTransactions($user_id, $limit = 50) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE user_id = :user_id 
                  ORDER BY created_at DESC 
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateTransactionStatus($transaction_id, $status) {
        $query = "UPDATE " . $this->table . " 
                  SET status = :status, updated_at = NOW() 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":id", $transaction_id);
        
        return $stmt->execute();
    }
}

// =======================
// Loan Manager Class
// =======================

class LoanManager {
    private $conn;
    private $table = "loans";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function createLoan($data) {
        $query = "INSERT INTO " . $this->table . " 
                  (user_id, loan_amount, interest_rate, loan_term_days, 
                   total_repayment, status, created_at, due_date) 
                  VALUES (:user_id, :amount, :rate, :term, :total, :status, NOW(), 
                          DATE_ADD(NOW(), INTERVAL :term DAY))";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $data['user_id']);
        $stmt->bindParam(":amount", $data['amount']);
        $stmt->bindParam(":rate", $data['rate']);
        $stmt->bindParam(":term", $data['term']);
        $stmt->bindParam(":total", $data['total']);
        $stmt->bindParam(":status", $data['status']);
        
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function getUserLoans($user_id) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE user_id = :user_id 
                  ORDER BY created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateLoanStatus($loan_id, $status) {
        $query = "UPDATE " . $this->table . " 
                  SET status = :status, updated_at = NOW() 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":id", $loan_id);
        
        return $stmt->execute();
    }

    public function getOverdueLoans() {
        $query = "SELECT l.*, u.wallet_address FROM " . $this->table . " l 
                  JOIN users u ON l.user_id = u.id 
                  WHERE l.due_date < NOW() AND l.status = 'active'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// =======================
// API Endpoints
// =======================

