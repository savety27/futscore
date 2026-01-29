<?php
$page_title = 'Team Staff List';
$current_page = 'team_staff';
require_once 'config/database.php';
require_once 'includes/header.php';

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Base Query (Read Only - Lists all staff)
$base_query = "SELECT 
    ts.id,
    ts.team_id,
    ts.name,
    ts.position,
    ts.email,
    ts.phone,
    ts.photo,
    ts.birth_date,
    t.name as team_name,
    (SELECT COUNT(*) FROM staff_certificates sc WHERE sc.staff_id = ts.id) as certificate_count
    FROM team_staff ts
    LEFT JOIN teams t ON ts.team_id = t.id
    WHERE 1=1";

$count_query = "SELECT COUNT(DISTINCT ts.id) as total FROM team_staff ts 
                LEFT JOIN teams t ON ts.team_id = t.id
                WHERE 1=1";

if (!empty($search)) {
    $search_term = "%{$search}%";
    $base_query .= " AND (ts.name LIKE ? OR ts.email LIKE ? OR ts.phone LIKE ? OR ts.position LIKE ? OR t.name LIKE ?)";
    $count_query .= " AND (ts.name LIKE ? OR ts.email LIKE ? OR ts.phone LIKE ? OR ts.position LIKE ? OR t.name LIKE ?)";
}

$base_query .= " GROUP BY ts.id ORDER BY ts.created_at DESC";

$total_data = 0;
$total_pages = 1;
$staff_list = [];

try {
    if (!empty($search)) {
        $stmt = $conn->prepare($count_query);
        $stmt->execute([$search_term, $search_term, $search_term, $search_term, $search_term]);
    } else {
        $stmt = $conn->prepare($count_query);
        $stmt->execute();
    }
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_data = $result['total'];
    
    $total_pages = ceil($total_data / $limit);
    
    $query = $base_query . " LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    
    if (!empty($search)) {
        $stmt->bindValue(1, $search_term);
        $stmt->bindValue(2, $search_term);
        $stmt->bindValue(3, $search_term);
        $stmt->bindValue(4, $search_term);
        $stmt->bindValue(5, $search_term);
        $stmt->bindValue(6, $limit, PDO::PARAM_INT);
        $stmt->bindValue(7, $offset, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $staff_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate Age
    foreach ($staff_list as &$staff) {
        if (!empty($staff['birth_date'])) {
            $birthDate = new DateTime($staff['birth_date']);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;
            $staff['age'] = $age;
        } else {
            $staff['age'] = '-';
        }
    }
    unset($staff);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<div class="card">
    <div class="section-header">
        <h2 class="section-title">Team Staff</h2>
         <!-- Read Only: No Add Button -->
    </div>

    <div class="search-bar" style="margin-bottom: 20px;">
        <form action="" method="GET">
            <input type="text" name="search" placeholder="Search staff..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <?php if (empty($staff_list)): ?>
        <p style="text-align: center; color: var(--gray); padding: 20px;">No staff found.</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="photo-cell">Photo</th>
                        <th>Name</th>
                        <th>Team</th>
                        <th style="text-align: center;">Position</th>
                        <th style="text-align: center;">Age</th>
                        <th style="text-align: center;">Certificates</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staff_list as $staff): ?>
                    <tr>
                         <td>
                            <img src="../images/staff/<?php echo $staff['photo']; ?>" alt="<?php echo $staff['name']; ?>" class="staff-photo" onerror="this.onerror=null; this.src='../images/staff/default-staff.png'">
                        </td>
                        <td class="name-cell">
                            <?php echo htmlspecialchars($staff['name']); ?>
                            <div style="font-size: 11px; color: var(--gray); font-weight: normal;"><?php echo htmlspecialchars($staff['email']); ?></div>
                        </td>
                        <td class="team-cell"><?php echo htmlspecialchars($staff['team_name']); ?></td>
                        <td class="position-cell">
                            <span class="position-badge"><?php echo htmlspecialchars($staff['position']); ?></span>
                        </td>
                        <td class="age-cell"><?php echo $staff['age']; ?></td>
                        <td class="certificate-cell">
                             <span class="certificate-count"><?php echo $staff['certificate_count']; ?> Certs</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="page-link">&laquo; Prev</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="page-link">Next &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
