<?php
// api/balance.php
include 'config.php';
if (basename($_SERVER['PHP_SELF']) == 'balance.php') {
    cors();
    
    $database = new Database();
    $db = $database->getConnection();
    $userManager = new UserManager($db);
    
    if($_SERVER['REQUEST_METHOD'] === 'GET') {
        if(!isset($_GET['wallet_address'])) {
            jsonResponse(['error' => 'Wallet address required'], 400);
        }
        
        $wallet_address = sanitizeInput($_GET['wallet_address']);
        $user = $userManager->getUserByWallet($wallet_address);
        
        if($user) {
            jsonResponse([
                'bank_balance' => floatval($user['bank_balance']),
                'last_updated' => $user['updated_at']
            ]);
        } else {
            jsonResponse(['error' => 'User not found'], 404);
        }
    } else {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
}
?>