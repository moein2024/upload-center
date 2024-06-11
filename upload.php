<?php
session_start();
require('/var/www/yourdomain.com/wp-load.php');

if (!is_user_logged_in()) {
    auth_redirect();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    $uploadDir = '/var/www/yourdomain.com/uploads/';
    $files = $_FILES['files'];
    $uploadedFiles = [];

    for ($i = 0; $i < count($files['name']); $i++) {
        $fileName = $files['name'][$i];
        $tmpName = $files['tmp_name'][$i];
        $fileSize = $files['size'][$i];
        $fileError = $files['error'][$i];

        if ($fileError !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'error', 'message' => 'File upload error']);
            exit;
        }

        $user = wp_get_current_user();
        $uniqueName = $user->ID . '_' . time() . '_' . basename($fileName);
        $uploadFile = $uploadDir . $uniqueName;

        if (move_uploaded_file($tmpName, $uploadFile)) {
            $fileUrl = '/uploads/' . $uniqueName;
            $uploadedFiles[] = ['name' => $fileName, 'url' => $fileUrl, 'size' => $fileSize, 'date' => date('Y-m-d H:i:s')];
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file']);
            exit;
        }
    }

    $existingFiles = json_decode(file_get_contents('files.json'), true);
    $allFiles = array_merge($existingFiles, $uploadedFiles);
    file_put_contents('files.json', json_encode($allFiles));

    echo json_encode(['status' => 'success', 'files' => $uploadedFiles]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $fileUrl = $_POST['delete'];
    $files = json_decode(file_get_contents('files.json'), true);
    foreach ($files as $key => $file) {
        if ($file['url'] === $fileUrl) {
            unlink('/var/www/yourdomain.com' . $fileUrl);
            unset($files[$key]);
            file_put_contents('files.json', json_encode(array_values($files)));
            echo json_encode(['status' => 'success']);
            exit;
        }
    }
    echo json_encode(['status' => 'error', 'message' => 'File not found']);
    exit;
}

function formatSizeUnits($bytes) {
    if ($bytes >= 1073741824) {
        $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        $bytes = number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        $bytes = number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        $bytes = $bytes . ' bytes';
    } elseif ($bytes == 1) {
        $bytes = $bytes . ' byte';
    } else {
        $bytes = '0 bytes';
    }

    return $bytes;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Upload</title>
    <!-- Add these lines in your head tag -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f0f2f5;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
            width: 100%;
            background-color: #fff;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 10px;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
            font-size: 24px;
        }
        form {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        input[type="file"] {
            margin-bottom: 20px;
        }
        button[type="submit"] {
            padding: 10px 20px;
            background-color: #4caf50;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button[type="submit"]:hover {
            background-color: #45a049;
        }
        .progress-bar {
            width: 100%;
            background-color: #e0e0e0;
            border-radius: 5px;
            overflow: hidden;
            height: 20px;
            margin-top: 10px;
        }
        .progress-bar-fill {
            height: 100%;
            background-color: #4caf50;
            width: 0%;
            transition: width 0.25s;
        }
        .buttons {
            text-align: center;
            margin-top: 20px;
        }
        .buttons a {
            text-decoration: none;
            color: #4caf50;
            font-weight: bold;
        }
        .buttons a:hover {
            color: #45a049;
        }
        h3 {
            margin-top: 30px;
            font-size: 20px;
        }
        .table-wrapper {
            max-height: 400px;
            overflow-y: auto;
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid #ccc;
        }
        th, td {
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #f4f4f4;
            cursor: pointer;
        }
        .search-bar input {
            width: calc(100% - 24px);
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .deleteBtn {
            background-color: #f44336;
            color: white;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
            border-radius: 5px;
        }
        .deleteBtn:hover {
            background-color: #e53935;
        }
        @media screen and (max-width: 600px) {
            .container {
                padding: 15px;
            }
            h2, h3 {
                font-size: 18px;
            }
            th, td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <h2>File Upload</h2>
    <form id="uploadForm" enctype="multipart/form-data">
        <input type="file" name="files[]" id="fileInput" multiple required>
        <button type="submit">Upload</button>
        <div class="progress-bar">
            <div class="progress-bar-fill"></div>
        </div>
    </form>
    <div class="buttons">
        <a href="<?php echo wp_logout_url(home_url()); ?>">Logout</a>
    </div>
    <h3>Uploaded Files</h3>
    <div class="table-wrapper">
        <table>
            <thead>
            <tr>
                <th onclick="sortTable(0)">File Name<br><input type="text" id="searchName" placeholder="Search by name"></th>
                <th onclick="sortTable(1)">Size<br><input type="text" id="searchSize" placeholder="Search by size"><br><small>&nbsp; < 80 KB &nbsp;&nbsp;&nbsp;&nbsp; or &nbsp;&nbsp;&nbsp;&nbsp; > 100 MB</small>
</th>
                <th onclick="sortTable(2)">Date Uploaded<br>
    <input type="text" id="searchDate" placeholder="Search by date">
    <select id="dateFilterType">
        <option value="exact">Exact Date</option>
        <option value="before">Before</option>
        <option value="after">After</option>
    </select>
</th>

                <th>Actions</th>
            </tr>
            </thead>
            <tbody id="filesTableBody">
            <?php
            $files = json_decode(file_get_contents('files.json'), true);
            $files = array_reverse($files);
            foreach ($files as $file) {
                echo "<tr>
                        <td>{$file['name']}</td>
                        <td>" . formatSizeUnits($file['size']) . "</td>
                        <td>{$file['date']}</td>
                        <td>
                            <a href='{$file['url']}' style='
    display: inline-block;
    padding: 3px 8px; /* Further reduced padding */
    background-color: #4CAF50;
    color: white;
    text-decoration: none;
    border-radius: 5px;
    border: 2px solid #4CAF50;
    font-family: Arial, sans-serif;
    font-size: 12px; /* Further reduced font size */
    transition: background-color 0.3s, color 0.3s;
'>Download</a>

                            <button class='deleteBtn' data-url='{$file['url']}'>Delete</button>
                        </td>
                      </tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$(function() {
    $("#searchDate").datepicker({
        dateFormat: "yy-mm-dd"
    });

    $("#searchDate").on("change", function() {
        filterTable(2, $(this).val(), $("#dateFilterType").val());
    });

    $("#dateFilterType").on("change", function() {
        filterTable(2, $("#searchDate").val(), $(this).val());
    });
});

document.getElementById('uploadForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const files = document.getElementById('fileInput').files;
    const formData = new FormData();
    for (let i = 0; i < files.length; i++) {
        formData.append('files[]', files[i]);
    }

    const xhr = new XMLHttpRequest();
    xhr.open('POST', '<?php echo $_SERVER['PHP_SELF']; ?>', true);
    xhr.upload.addEventListener('progress', function (e) {
        if (e.lengthComputable) {
            const percentComplete = (e.loaded / e.total) * 100;
            document.querySelector('.progress-bar-fill').style.width = percentComplete + '%';
        }
    });

    xhr.onload = function () {
        if (xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            if (response.status === 'success') {
                alert('Files uploaded successfully');
                location.reload();
            } else {
                alert('Error: ' + response.message);
            }
        } else {
            alert('An error occurred while uploading the files.');
        }
    };

    xhr.send(formData);
});

document.getElementById('filesTableBody').addEventListener('click', function (e) {
    if (e.target.classList.contains('deleteBtn')) {
        const fileUrl = e.target.getAttribute('data-url');
        if (confirm('Are you sure you want to delete this file?')) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo $_SERVER['PHP_SELF']; ?>', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function () {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.status === 'success') {
                        alert('File deleted successfully');
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                } else {
                    alert('An error occurred while deleting the file.');
                }
            };
            xhr.send('delete=' + encodeURIComponent(fileUrl));
        }
    }
});

document.getElementById('searchName').addEventListener('input', function () {
    filterTable(0, this.value);
});
document.getElementById('searchSize').addEventListener('input', function () {
    filterTable(1, this.value);
});
document.getElementById('searchDate').addEventListener('input', function () {
    filterTable(2, this.value, $("#dateFilterType").val());
});

function convertSizeToBytes(size) {
    const sizeUnits = size.split(' ');
    const number = parseFloat(sizeUnits[0]);
    const unit = sizeUnits[1].toUpperCase();

    switch(unit) {
        case 'GB':
            return number * 1073741824;
        case 'MB':
            return number * 1048576;
        case 'KB':
            return number * 1024;
        default:
            return number;
    }
}

function filterTable(colIndex, filterValue, filterType = null) {
    const table = document.querySelector('table tbody');
    const rows = table.querySelectorAll('tr');
    rows.forEach(row => {
        const cell = row.querySelectorAll('td')[colIndex];
        if (cell) {
            const text = cell.textContent.toLowerCase();
            if (colIndex === 1 && (filterValue.includes('>') || filterValue.includes('<'))) {
                const operator = filterValue.includes('>') ? '>' : '<';
                const sizeFilter = convertSizeToBytes(filterValue.replace(/[><]/g, '').trim());
                const cellSize = convertSizeToBytes(cell.textContent);
                if (operator === '>' && cellSize > sizeFilter || operator === '<' && cellSize < sizeFilter) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            } else if (colIndex === 2 && filterType) {
                const cellDate = new Date(cell.textContent);
                const filterDate = new Date(filterValue);

                if (filterType === 'before' && cellDate < filterDate ||
                    filterType === 'after' && cellDate > filterDate ||
                    filterType === 'exact' && cell.textContent.includes(filterValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            } else if (text.includes(filterValue.toLowerCase())) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
}

let sortDirection = [true, true, true];

function sortTable(colIndex) {
    const table = document.querySelector('table tbody');
    const rows = Array.from(table.rows);
    const direction = sortDirection[colIndex] ? 1 : -1;

    const sortedRows = rows.sort((a, b) => {
        const aText = a.cells[colIndex].innerText;
        const bText = b.cells[colIndex].innerText;

        if (colIndex === 1) {
            const aSize = convertSizeToBytes(aText);
            const bSize = convertSizeToBytes(bText);
            return direction * (aSize - bSize);
        }

        if (colIndex === 2) {
            const aDate = new Date(aText);
            const bDate = new Date(bText);
            return direction * (aDate - bDate);
        }

        return direction * aText.localeCompare(bText);
    });

    while (table.firstChild) {
        table.removeChild(table.firstChild);
    }

    sortedRows.forEach(row => table.appendChild(row));
    sortDirection[colIndex] = !sortDirection[colIndex];
}
</script>

</body>
</html>


