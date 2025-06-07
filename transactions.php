<?php
// api/transactions.php
include 'config.php';
if (basename($_SERVER['PHP_SELF']) == 'transactions.php') {
    cors();
    
    $database = new Database();
    $db = $database->getConnection();
    $transactionManager = new TransactionManager($db);
    $userManager = new UserManager($db);
    
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch($method) {
        case 'GET':
            if(!isset($_GET['wallet_address'])) {
                jsonResponse(['error' => 'Wallet address required'], 400);
            }
            
            $wallet_address = sanitizeInput($_GET['wallet_address']);
            $user = $userManager->getUserByWallet($wallet_address);
            
            if(!$user) {
                jsonResponse(['error' => 'User not found'], 404);
            }
            
            $transactions = $transactionManager->getUserTransactions($user['id']);
            jsonResponse(['transactions' => $transactions]);
            break;
            
        case 'POST':
            if(!isset($input['wallet_address']) || !isset($input['type'])) {
                jsonResponse(['error' => 'Missing required fields'], 400);
            }
            
            $wallet_address = sanitizeInput($input['wallet_address']);
            $user = $userManager->getUserByWallet($wallet_address);
            
            if(!$user) {
                jsonResponse(['error' => 'User not found'], 404);
            }
            
            $transactionData = [
                'user_id' => $user['id'],
                'type' => sanitizeInput($input['type']),
                'amount' => floatval($input['amount']),
                'recipient' => isset($input['recipient']) ? sanitizeInput($input['recipient']) : null,
                'hash' => sanitizeInput($input['hash']),
                'status' => isset($input['status']) ? sanitizeInput($input['status']) : 'pending',
                'details' => isset($input['details']) ? sanitizeInput($input['details']) : null
            ];
            
            $transaction_id = $transactionManager->createTransaction($transactionData);
            
            if($transaction_id) {
                // Update user balance for deposits
                if($input['type'] === 'deposit') {
                    $new_balance = $user['bank_balance'] + floatval($input['amount']);
                    $userManager->updateBalance($wallet_address, $new_balance);
                }
                
                jsonResponse([
                    'message' => 'Transaction recorded successfully',
                    'transaction_id' => $transaction_id
                ]);
            } else {
                jsonResponse(['error' => 'Failed to record transaction'], 500);
            }
            break;
            
        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
}

