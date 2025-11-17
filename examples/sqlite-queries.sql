-- Digital Wallet API - Consultas SQLite Úteis
-- Execute: sqlite3 database/database.sqlite < examples/sqlite-queries.sql

-- ============================================
-- CONSULTAS BÁSICAS
-- ============================================

-- Listar todos os usuários
SELECT
  id,
  name,
  email,
  created_at
FROM users
ORDER BY created_at DESC;

-- Listar todas as contas com saldo
SELECT
  u.name as usuario,
  a.account_number as conta,
  a.agency as agencia,
  a.account || '-' || a.account_digit as numero_conta,
  a.account_type as tipo,
  a.status,
  PRINTF('R$ %.2f', b.amount) as saldo
FROM accounts a
JOIN users u ON a.user_id = u.id
JOIN balances b ON a.id = b.account_id
ORDER BY b.amount DESC;

-- ============================================
-- TRANSAÇÕES
-- ============================================

-- Últimas 10 transações de uma conta
SELECT
  t.id,
  t.transaction_id as codigo,
  tt.name as tipo,
  CASE
    WHEN t.flow = 'C' THEN '+ Crédito'
    WHEN t.flow = 'D' THEN '- Débito'
    WHEN t.flow = 'E' THEN '↺ Estorno'
  END as fluxo,
  PRINTF('R$ %.2f', t.amount) as valor,
  PRINTF('R$ %.2f', t.balance_after) as saldo_depois,
  t.description as descricao,
  datetime(t.created_at) as data
FROM transactions t
JOIN transaction_types tt ON t.transaction_type_id = tt.id
WHERE t.account_id = 1
ORDER BY t.created_at DESC
LIMIT 10;

-- Total de transações por tipo
SELECT
  tt.name as tipo_transacao,
  COUNT(*) as quantidade,
  PRINTF('R$ %.2f', SUM(t.amount)) as valor_total,
  PRINTF('R$ %.2f', AVG(t.amount)) as valor_medio
FROM transactions t
JOIN transaction_types tt ON t.transaction_type_id = tt.id
GROUP BY tt.name
ORDER BY quantidade DESC;

-- Transações do dia
SELECT
  u.name as usuario,
  a.account_number as conta,
  tt.name as tipo,
  PRINTF('R$ %.2f', t.amount) as valor,
  t.description as descricao,
  time(t.created_at) as hora
FROM transactions t
JOIN accounts a ON t.account_id = a.id
JOIN users u ON a.user_id = u.id
JOIN transaction_types tt ON t.transaction_type_id = tt.id
WHERE DATE(t.created_at) = DATE('now')
ORDER BY t.created_at DESC;

-- ============================================
-- TRANSFERÊNCIAS
-- ============================================

-- Listar todas as transferências
SELECT
  tf.id,
  tf.transaction_id as codigo,
  sender.name as remetente,
  acc_sender.account_number as conta_origem,
  receiver.name as destinatario,
  acc_receiver.account_number as conta_destino,
  PRINTF('R$ %.2f', tf.amount) as valor,
  tf.description as descricao,
  tf.status,
  datetime(tf.created_at) as data
FROM transfers tf
JOIN accounts acc_sender ON tf.sender_account_id = acc_sender.id
JOIN accounts acc_receiver ON tf.receiver_account_id = acc_receiver.id
JOIN users sender ON acc_sender.user_id = sender.id
JOIN users receiver ON acc_receiver.user_id = receiver.id
ORDER BY tf.created_at DESC;

-- Transferências recebidas por uma conta
SELECT
  sender.name as remetente,
  acc_sender.account_number as conta_origem,
  PRINTF('R$ %.2f', tf.amount) as valor,
  tf.description as descricao,
  datetime(tf.created_at) as data
FROM transfers tf
JOIN accounts acc_sender ON tf.sender_account_id = acc_sender.id
JOIN users sender ON acc_sender.user_id = sender.id
WHERE tf.receiver_account_id = 1
ORDER BY tf.created_at DESC;

-- Transferências enviadas por uma conta
SELECT
  receiver.name as destinatario,
  acc_receiver.account_number as conta_destino,
  PRINTF('R$ %.2f', tf.amount) as valor,
  tf.description as descricao,
  datetime(tf.created_at) as data
FROM transfers tf
JOIN accounts acc_receiver ON tf.receiver_account_id = acc_receiver.id
JOIN users receiver ON acc_receiver.user_id = receiver.id
WHERE tf.sender_account_id = 1
ORDER BY tf.created_at DESC;

-- ============================================
-- LIMITES DIÁRIOS
-- ============================================

-- Consultar limites diários de uma conta
SELECT
  a.account_number as conta,
  dl.limit_type as tipo_limite,
  PRINTF('R$ %.2f', dl.daily_limit) as limite_diario,
  PRINTF('R$ %.2f', dl.current_used) as usado,
  PRINTF('R$ %.2f', dl.daily_limit - dl.current_used) as disponivel,
  PRINTF('%.1f%%', (dl.current_used * 100.0 / dl.daily_limit)) as percentual_usado,
  dl.reset_at as reseta_em
FROM daily_limits dl
JOIN accounts a ON dl.account_id = a.id
WHERE a.id = 1
  AND dl.reset_at = DATE('now')
ORDER BY dl.limit_type;

-- Contas que atingiram 80% do limite
SELECT
  u.name as usuario,
  a.account_number as conta,
  dl.limit_type as tipo_limite,
  PRINTF('R$ %.2f', dl.daily_limit) as limite,
  PRINTF('R$ %.2f', dl.current_used) as usado,
  PRINTF('%.1f%%', (dl.current_used * 100.0 / dl.daily_limit)) as percentual
FROM daily_limits dl
JOIN accounts a ON dl.account_id = a.id
JOIN users u ON a.user_id = u.id
WHERE dl.reset_at = DATE('now')
  AND (dl.current_used * 100.0 / dl.daily_limit) >= 80
ORDER BY percentual DESC;

-- ============================================
-- ANÁLISES E RELATÓRIOS
-- ============================================

-- Extrato consolidado de uma conta
SELECT
  DATE(t.created_at) as data,
  COUNT(*) as num_transacoes,
  PRINTF('R$ %.2f', SUM(CASE WHEN t.flow = 'C' THEN t.amount ELSE 0 END)) as total_creditos,
  PRINTF('R$ %.2f', SUM(CASE WHEN t.flow = 'D' THEN t.amount ELSE 0 END)) as total_debitos,
  PRINTF('R$ %.2f',
    SUM(CASE WHEN t.flow = 'C' THEN t.amount ELSE 0 END) -
    SUM(CASE WHEN t.flow = 'D' THEN t.amount ELSE 0 END)
  ) as saldo_liquido
FROM transactions t
WHERE t.account_id = 1
  AND t.flow IN ('C', 'D')
GROUP BY DATE(t.created_at)
ORDER BY data DESC
LIMIT 30;

-- Ranking de usuários por saldo
SELECT
  ROW_NUMBER() OVER (ORDER BY b.amount DESC) as ranking,
  u.name as usuario,
  a.account_number as conta,
  PRINTF('R$ %.2f', b.amount) as saldo,
  (SELECT COUNT(*) FROM transactions WHERE account_id = a.id) as num_transacoes
FROM users u
JOIN accounts a ON u.id = a.user_id
JOIN balances b ON a.id = b.account_id
WHERE a.status = 'active'
ORDER BY b.amount DESC
LIMIT 10;

-- Transações estornadas
SELECT
  t_original.id as id_original,
  t_original.transaction_id as codigo_original,
  tt_original.name as tipo_original,
  PRINTF('R$ %.2f', t_original.amount) as valor,
  t_estorno.id as id_estorno,
  t_estorno.transaction_id as codigo_estorno,
  t_original.description as descricao_original,
  datetime(t_original.created_at) as data_original,
  datetime(t_estorno.created_at) as data_estorno
FROM transactions t_original
JOIN transaction_types tt_original ON t_original.transaction_type_id = tt_original.id
JOIN transactions t_estorno ON t_estorno.chargeback_of_transaction_id = t_original.id
WHERE t_original.is_chargebacked = 1
ORDER BY t_estorno.created_at DESC;

-- Transações contestadas
SELECT
  u.name as usuario,
  a.account_number as conta,
  t.transaction_id as codigo,
  tt.name as tipo,
  PRINTF('R$ %.2f', t.amount) as valor,
  t.contested_reason as motivo,
  datetime(t.created_at) as data_transacao,
  datetime(t.contested_at) as data_contestacao
FROM transactions t
JOIN accounts a ON t.account_id = a.id
JOIN users u ON a.user_id = u.id
JOIN transaction_types tt ON t.transaction_type_id = tt.id
WHERE t.is_contested = 1
ORDER BY t.contested_at DESC;

-- Movimentação geral do dia
SELECT
  'Depósitos' as operacao,
  COUNT(*) as quantidade,
  PRINTF('R$ %.2f', SUM(t.amount)) as valor_total
FROM transactions t
JOIN transaction_types tt ON t.transaction_type_id = tt.id
WHERE DATE(t.created_at) = DATE('now')
  AND tt.code = 'DEPOSIT'

UNION ALL

SELECT
  'Saques' as operacao,
  COUNT(*) as quantidade,
  PRINTF('R$ %.2f', SUM(t.amount)) as valor_total
FROM transactions t
JOIN transaction_types tt ON t.transaction_type_id = tt.id
WHERE DATE(t.created_at) = DATE('now')
  AND tt.code = 'WITHDRAW'

UNION ALL

SELECT
  'Transferências' as operacao,
  COUNT(*) as quantidade,
  PRINTF('R$ %.2f', SUM(tf.amount)) as valor_total
FROM transfers tf
WHERE DATE(tf.created_at) = DATE('now');

-- ============================================
-- AUDITORIA E SEGURANÇA
-- ============================================

-- Contas inativas ou bloqueadas
SELECT
  u.name as usuario,
  u.email,
  a.account_number as conta,
  a.status,
  PRINTF('R$ %.2f', b.amount) as saldo,
  datetime(a.updated_at) as ultima_atualizacao
FROM accounts a
JOIN users u ON a.user_id = u.id
JOIN balances b ON a.id = b.account_id
WHERE a.status IN ('inactive', 'blocked')
ORDER BY a.updated_at DESC;

-- Transações de alto valor (acima de R$ 1.000)
SELECT
  u.name as usuario,
  a.account_number as conta,
  tt.name as tipo,
  PRINTF('R$ %.2f', t.amount) as valor,
  t.description as descricao,
  datetime(t.created_at) as data
FROM transactions t
JOIN accounts a ON t.account_id = a.id
JOIN users u ON a.user_id = u.id
JOIN transaction_types tt ON t.transaction_type_id = tt.id
WHERE t.amount >= 1000
ORDER BY t.amount DESC;

-- Histórico de alterações de saldo (últimas 20)
SELECT
  u.name as usuario,
  a.account_number as conta,
  tt.name as operacao,
  PRINTF('R$ %.2f', t.balance_before) as saldo_antes,
  PRINTF('R$ %.2f', t.amount) as valor_operacao,
  PRINTF('R$ %.2f', t.balance_after) as saldo_depois,
  datetime(t.created_at) as data
FROM transactions t
JOIN accounts a ON t.account_id = a.id
JOIN users u ON a.user_id = u.id
JOIN transaction_types tt ON t.transaction_type_id = tt.id
ORDER BY t.created_at DESC
LIMIT 20;
