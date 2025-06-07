-- crypto_bank.sql
-- Database Schema for Crypto Banking System

CREATE DATABASE IF NOT EXISTS crypto_bank;
USE crypto_bank;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wallet_address VARCHAR(42) UNIQUE NOT NULL,
    email VARCHAR(255) NULL,
    bank_balance DECIMAL(18, 8) DEFAULT 0.00000000,
    total_deposits DECIMAL(18, 8) DEFAULT 0.00000000,
    total_withdrawals DECIMAL(18, 8) DEFAULT 0.00000000,
    kyc_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    account_status ENUM('active', 'suspended', 'closed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_wallet_address (wallet_address),
    INDEX idx_created_at (created_at)
);

-- Transactions table
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    transaction_type ENUM('deposit', 'withdrawal', 'send', 'receive', 'loan', 'repayment') NOT NULL,
    amount DECIMAL(18, 8) NOT NULL,
    recipient_address VARCHAR(42) NULL,
    transaction_hash VARCHAR(66) NULL,
    status ENUM('pending', 'confirmed', 'failed', 'cancelled') DEFAULT 'pending',
    details TEXT NULL,
    gas_fee DECIMAL(18, 8) DEFAULT 0.00000000,
    block_number BIGINT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_transaction_hash (transaction_hash)
);

-- Loans table
CREATE TABLE loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    loan_amount DECIMAL(18, 8) NOT NULL,
    interest_rate DECIMAL(5, 2) NOT NULL,
    loan_term_days INT NOT NULL,
    total_repayment DECIMAL(18, 8) NOT NULL,
    amount_repaid DECIMAL(18, 8) DEFAULT 0.00000000,
    status ENUM('pending', 'approved', 'active', 'repaid', 'defaulted', 'cancelled') DEFAULT 'pending',
    collateral_amount DECIMAL(18, 8) DEFAULT 0.00000000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    due_date TIMESTAMP NOT NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date),
    INDEX idx_created_at (created_at)
);

-- Loan payments table
CREATE TABLE loan_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    payment_amount DECIMAL(18, 8) NOT NULL,
    transaction_hash VARCHAR(66) NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'confirmed', 'failed') DEFAULT 'pending',
    
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
    INDEX idx_loan_id (loan_id),
    INDEX idx_payment_date (payment_date)
);

-- Interest rates table (for dynamic rate management)
CREATE TABLE interest_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_term_days INT NOT NULL,
    annual_rate DECIMAL(5, 2) NOT NULL,
    min_amount DECIMAL(18, 8) DEFAULT 0.00000000,
    max_amount DECIMAL(18, 8) DEFAULT 999999.99999999,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_loan_term (loan_term_days),
    INDEX idx_is_active (is_active)
);

-- Admin settings table
CREATE TABLE admin_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Security logs table
CREATE TABLE security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    action VARCHAR(100) NOT NULL,
    details JSON NULL,
    risk_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_risk_level (risk_level),
    INDEX idx_created_at (created_at)
);

-- Insert default interest rates
INSERT INTO interest_rates (loan_term_days, annual_rate, min_amount, max_amount) VALUES
(30, 5.00, 0.1, 10.0),
(90, 8.00, 0.1, 50.0),
(180, 12.00, 0.1, 100.0),
(365, 15.00, 1.0, 500.0);

-- Insert default admin settings
INSERT INTO admin_settings (setting_key, setting_value, description) VALUES
('max_loan_amount', '100.0', 'Maximum loan amount in ETH'),
('min_loan_amount', '0.1', 'Minimum loan amount in ETH'),
('max_daily_transactions', '50', 'Maximum transactions per user per day'),
('maintenance_mode', 'false', 'Enable/disable maintenance mode'),
('gas_fee_multiplier', '1.2', 'Gas fee multiplier for transactions'),
('default_loan_term', '90', 'Default loan term in days');

-- Create views for better data access
CREATE VIEW user_stats AS
SELECT 
    u.id,
    u.wallet_address,
    u.bank_balance,
    COUNT(DISTINCT t.id) as total_transactions,
    COUNT(DISTINCT l.id) as total_loans,
    COALESCE(SUM(CASE WHEN l.status = 'active' THEN l.loan_amount ELSE 0 END), 0) as active_loans,
    u.created_at as member_since
FROM users u
LEFT JOIN transactions t ON u.id = t.user_id
LEFT JOIN loans l ON u.id = l.user_id
GROUP BY u.id;

CREATE VIEW transaction_summary AS
SELECT 
    DATE(created_at) as transaction_date,
    transaction_type,
    COUNT(*) as transaction_count,
    SUM(amount) as total_amount,
    AVG(amount) as average_amount
FROM transactions
WHERE status = 'confirmed'
GROUP BY DATE(created_at), transaction_type
ORDER BY transaction_date DESC;

-- Stored procedures for common operations
DELIMITER //

CREATE PROCEDURE ProcessLoanRepayment(
    IN p_loan_id INT,
    IN p_payment_amount DECIMAL(18,8),
    IN p_transaction_hash VARCHAR(66)
)
BEGIN
    DECLARE v_user_id INT;
    DECLARE v_remaining_amount DECIMAL(18,8);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Get loan details
    SELECT user_id, (total_repayment - amount_repaid) INTO v_user_id, v_remaining_amount
    FROM loans WHERE id = p_loan_id AND status = 'active';
    
    -- Record payment
    INSERT INTO loan_payments (loan_id, payment_amount, transaction_hash, status)
    VALUES (p_loan_id, p_payment_amount, p_transaction_hash, 'confirmed');
    
    -- Update loan
    UPDATE loans 
    SET amount_repaid = amount_repaid + p_payment_amount,
        status = CASE 
            WHEN (amount_repaid + p_payment_amount) >= total_repayment THEN 'repaid'
            ELSE 'active'
        END,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = p_loan_id;
    
    -- Record transaction
    INSERT INTO transactions (user_id, transaction_type, amount, transaction_hash, status, details)
    VALUES (v_user_id, 'repayment', p_payment_amount, p_transaction_hash, 'confirmed', 
            CONCAT('Loan repayment for loan ID: ', p_loan_id));
    
    COMMIT;
END //

CREATE PROCEDURE GetUserDashboard(IN p_wallet_address VARCHAR(42))
BEGIN
    SELECT 
        u.wallet_address,
        u.bank_balance,
        u.account_status,
        COUNT(DISTINCT t.id) as total_transactions,
        COUNT(DISTINCT l.id) as total_loans,
        COALESCE(SUM(CASE WHEN l.status = 'active' THEN l.total_repayment - l.amount_repaid ELSE 0 END), 0) as outstanding_debt,
        (SELECT COUNT(*) FROM transactions WHERE user_id = u.id AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as transactions_30_days
    FROM users u
    LEFT JOIN transactions t ON u.id = t.user_id
    LEFT JOIN loans l ON u.id = l.user_id
    WHERE u.wallet_address = p_wallet_address
    GROUP BY u.id;
END //

DELIMITER ;

-- Triggers for audit trail
CREATE TRIGGER balance_update_trigger
    AFTER UPDATE ON users
    FOR EACH ROW
    BEGIN
        IF OLD.bank_balance != NEW.bank_balance THEN
            INSERT INTO security_logs (user_id, ip_address, action, details)
            VALUES (NEW.id, '127.0.0.1', 'balance_updated', 
                    JSON_OBJECT('old_balance', OLD.bank_balance, 'new_balance', NEW.bank_balance));
        END IF;
    END;

-- Indexes for performance optimization
CREATE INDEX idx_transactions_user_date ON transactions(user_id, created_at);
CREATE INDEX idx_loans_user_status ON loans(user_id, status);
CREATE INDEX idx_users_balance ON users(bank_balance);

-- Sample data for testing (optional)
-- INSERT INTO users (wallet_address, email, bank_balance) VALUES
-- ('0x1234567890123456789012345678901234567890', 'test@example.com', 5.50000000),
-- ('0x2345678901234567890123456789012345678901', 'user2@example.com', 10.25000000);

SHOW TABLES;