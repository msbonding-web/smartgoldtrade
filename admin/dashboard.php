<?php require_once 'header.php'; ?>

<div class="grid-container">
    <!-- Stat Card 1 -->
    <div class="card stat-card">
        <div class="icon" style="background-color: #2980b9;">👥</div>
        <div class="info">
            <h4>Total Users</h4>
            <p>1,250</p>
        </div>
    </div>
    <!-- Stat Card 2 -->
    <div class="card stat-card">
        <div class="icon" style="background-color: #27ae60;">💹</div>
        <div class="info">
            <h4>Total Investment</h4>
            <p>$550,240</p>
        </div>
    </div>
    <!-- Stat Card 3 -->
    <div class="card stat-card">
        <div class="icon" style="background-color: #f39c12;">📊</div>
        <div class="info">
            <h4>Trading Volume (24h)</h4>
            <p>$1.2M</p>
        </div>
    </div>
    <!-- Stat Card 4 -->
    <div class="card stat-card">
        <div class="icon" style="background-color: #c0392b;">❗</div>
        <div class="info">
            <h4>Pending KYC</h4>
            <p>15</p>
        </div>
    </div>
</div>

<div class="grid-container" style="grid-template-columns: 2fr 1fr;">
    <div class="card">
        <h4>Live Gold Price (XAU/USD)</h4>
        <!-- Placeholder for a live chart -->
        <img src="https://i.imgur.com/t3b2dYn.png" alt="Chart Placeholder" style="width: 100%;">
    </div>
    <div class="card">
        <h4>Quick Actions</h4>
        <button class="btn btn-primary" style="width: 100%; margin-bottom: 1rem;">Approve Deposits</button>
        <button class="btn btn-success" style="width: 100%; margin-bottom: 1rem;">Approve Withdrawals</button>
        <button class="btn btn-danger" style="width: 100%;">Manage P2P Disputes</button>
    </div>
</div>

<div class="card">
    <h4>Recent Transactions</h4>
    <table class="table">
        <thead>
            <tr>
                <th>User</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>user@example.com</td>
                <td>Deposit</td>
                <td>$500.00</td>
                <td><span style="color: var(--success);">Completed</span></td>
                <td>2025-08-16</td>
            </tr>
            <tr>
                <td>another@example.com</td>
                <td>Withdrawal</td>
                <td>$250.00</td>
                <td><span style="color: var(--warning);">Pending</span></td>
                <td>2025-08-16</td>
            </tr>
            <tr>
                <td>trader@example.com</td>
                <td>Investment</td>
                <td>$1,000.00</td>
                <td><span style="color: var(--success);">Active</span></td>
                <td>2025-08-15</td>
            </tr>
        </tbody>
    </table>
</div>

<?php require_once 'footer.php'; ?>
