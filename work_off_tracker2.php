<?php
// Database connection and class definition
class work_off_tracker2 {
    private $pdo;

    public function __construct($servername, $username, $password, $dbname) {
        date_default_timezone_set("Asia/Tashkent");
        $this->pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    }

    private function calculate_seconds_to_hour($sec) {
        return floor($sec / 3600);
    }

    public function addEntry($arrived_at, $leaved_at) {
        if (!empty($arrived_at) && !empty($leaved_at)) {
            $arrivedat = new DateTime($arrived_at);
            $leavedat = new DateTime($leaved_at);

            $work_off_time_sum = 0;
            $entitled_time_sum = 0;

            $arrivedatFormatted = $arrivedat->format("Y-m-d H:i:s");
            $leavedatFormatted = $leavedat->format("Y-m-d H:i:s");

            $interval = $arrivedat->diff($leavedat);
            $workingDurationSeconds = ($interval->h * 3600) + ($interval->i * 60) + $interval->s;

            $const_work_time = 32400;

            if ($workingDurationSeconds > $const_work_time){
                $debted_time = $workingDurationSeconds - $const_work_time;
                $req_work_off_timee = $this->calculate_seconds_to_hour($debted_time);
                $work_off_time_sum += $req_work_off_timee;
            } else if ($workingDurationSeconds < $const_work_time){
                $debted_time = $const_work_time - $workingDurationSeconds;
                $entitled = $this->calculate_seconds_to_hour($debted_time);
                $entitled_time_sum += $entitled;
            }

            $query = $this->pdo->query("SELECT * FROM Daily")->fetchAll();

            $new_entitled_time_sum = 0;
            $new_work_off_time_sum = 0;
            foreach ($query as $row) {
                $new_entitled_time_sum += $row['req_work_off_time_sum'];
                $new_work_off_time_sum += $row['entitled_time_sum'];
            }

            if($work_off_time_sum > $entitled_time_sum){
                $new_work_off_time_sum = $work_off_time_sum - $entitled_time_sum;
            }elseif ($work_off_time_sum < $entitled_time_sum) {
                $new_entitled_time_sum = $entitled_time_sum - $work_off_time_sum;
            }else{
                $new_work_off_time_sum = 0;
                $new_entitled_time_sum = 0;
            }

            $workingDurationSeconds = $this->calculate_seconds_to_hour($workingDurationSeconds);

            $sql = "INSERT INTO Daily (arrived_at, leaved_at, working_duration, req_work_off_time, entitled, req_work_off_time_sum, entitled_time_sum) VALUES (:arrived_at, :leaved_at, :working_duration, :req_work_off_time, :entitled, :req_work_off_time_sum, :entitled_time_sum)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':arrived_at', $arrivedatFormatted);
            $stmt->bindParam(':leaved_at', $leavedatFormatted);
            $stmt->bindParam(':working_duration', $workingDurationSeconds);
            $stmt->bindParam(':req_work_off_time', $entitled);
            $stmt->bindParam(':entitled', $req_work_off_timee);
            $stmt->bindParam(':req_work_off_time_sum', $new_entitled_time_sum);
            $stmt->bindParam(':entitled_time_sum', $new_work_off_time_sum);
            $stmt->execute();
            return "Dates successfully added.";
        } else {
            return "Please fill the gaps!";
        }
    }

    public function markAsDone($id) {
        $sql = "UPDATE Daily SET done = 1 WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
    }

    public function displayEntries($page, $limit) {
        $offset = ($page - 1) * $limit;
        $query = $this->pdo->prepare("SELECT * FROM Daily LIMIT :limit OFFSET :offset");
        $query->bindParam(':limit', $limit, PDO::PARAM_INT);
        $query->bindParam(':offset', $offset, PDO::PARAM_INT);
        $query->execute();
        $rows = $query->fetchAll();

        foreach ($rows as $row) {
            $done = $row['done'] ? 'checked' : '';
            $rowClass = $row['done'] ? 'table-success' : '';
            echo "<tr class='$rowClass'>
                    <td>{$row['id']}</td>
                    <td>{$row['arrived_at']}</td>
                    <td>{$row['leaved_at']}</td>
                    <td>{$row['working_duration']} Hours</td>
                    <td>{$row['req_work_off_time']}</td>
                    <td>{$row['entitled']}</td>
                    <td>{$row['req_work_off_time_sum']}</td>
                    <td>{$row['entitled_time_sum']}</td>
                    <td><input type='checkbox' data-id='{$row['id']}' class='done-checkbox' $done></td>
                  </tr>";
        }

        $totalQuery = $this->pdo->query("SELECT COUNT(*) as total FROM Daily");
        $totalRows = $totalQuery->fetch(PDO::FETCH_ASSOC)['total'];
        return $totalRows;
    }

    public function getSummary() {
        $query = $this->pdo->query("SELECT SUM(req_work_off_time_sum) as req_sum, SUM(entitled_time_sum) as ent_sum FROM Daily")->fetch(PDO::FETCH_ASSOC);
        return $query;
    }

    public function exportToCSV() {
        $filename = "report_" . date("Y-m-d_H-i-s") . ".csv";
        $file = fopen('php://output', 'w');

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        $header = ['ID', 'Arrived At', 'Leaved At', 'Working Duration', 'Req Work Off Time', 'Entitled', 'Req Work Off Time Sum', 'Entitled Time Sum', 'Done'];
        fputcsv($file, $header);

        $query = $this->pdo->query("SELECT * FROM Daily");
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($file, $row);
        }

        fclose($file);
        exit;
    }
}

$servername = "localhost";
$username = "root";
$password = "1234";
$dbname = "work_off_tracker";

$workOffTracker = new work_off_tracker2($servername, $username, $password, $dbname);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['arrived_at'], $_POST['leaved_at'])) {
        echo $workOffTracker->addEntry($_POST['arrived_at'], $_POST['leaved_at']);
    } elseif (isset($_POST['done_id'])) {
        $workOffTracker->markAsDone($_POST['done_id']);
    }
} elseif (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $workOffTracker->exportToCSV();
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$totalRows = $workOffTracker->displayEntries($page, $limit);
$totalPages = ceil($totalRows / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Off Tracker</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h1 class="mb-4">Work Off Tracker</h1>

    <form method="POST" class="mb-4">
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="arrived_at">Arrived At</label>
                <input type="datetime-local" class="form-control" id="arrived_at" name="arrived_at" required>
            </div>
            <div class="form-group col-md-6">
                <label for="leaved_at">Leaved At</label>
                <input type="datetime-local" class="form-control" id="leaved_at" name="leaved_at" required>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Add Entry</button>
        <a href="?export=csv" class="btn btn-success">Export CSV</a>
    </form>

    <table class="table table-striped">
        <thead>
        <tr>
            <th>ID</th>
            <th>Arrived At</th>
            <th>Leaved At</th>
            <th>Working Duration</th>
            <th>Req Work Off Time</th>
            <th>Entitled</th>
            <th>Req Work Off Time Sum</th>
            <th>Entitled Time Sum</th>
            <th>Done</th>
        </tr>
        </thead>
        <tbody>
        <?php $workOffTracker->displayEntries($page, $limit); ?>
        </tbody>
    </table>

    <div class="d-flex justify-content-between mb-4">
        <button id="prev" class="btn btn-secondary" <?php if ($page <= 1) echo 'disabled'; ?>>Previous</button>
        <button id="next" class="btn btn-secondary" <?php if ($page >= $totalPages) echo 'disabled'; ?>>Next</button>
    </div>

    <div class="alert alert-info">
        <?php
        $summary = $workOffTracker->getSummary();
        echo "Total Req Work Off Time Sum: {$summary['req_sum']} | Total Entitled Time Sum: {$summary['ent_sum']}";
        ?>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalLabel">Confirmation</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Are you sure you want to mark this entry as done?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmButton">Confirm</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let currentCheckbox = null;

    document.querySelectorAll('.done-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            currentCheckbox = this;
            $('#confirmModal').modal('show');
        });
    });

    document.getElementById('confirmButton').addEventListener('click', function () {
        const id = currentCheckbox.getAttribute('data-id');
        const formData = new FormData();
        formData.append('done_id', id);

        fetch('work_off_tracker2.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.text())
            .then(data => location.reload());

        $('#confirmModal').modal('hide');
    });

    document.getElementById('prev').addEventListener('click', function () {
        const page = new URLSearchParams(window.location.search).get('page') || 1;
        if (page > 1) {
            window.location.href = `work_off_tracker2.php?page=${page - 1}`;
        }
    });

    document.getElementById('next').addEventListener('click', function () {
        const page = new URLSearchParams(window.location.search).get('page') || 1;
        const totalPages = <?php echo $totalPages; ?>;
        if (page < totalPages) {
            window.location.href = `work_off_tracker2.php?page=${page + 1}`;
        }
    });
</script>
</body>
</html>
