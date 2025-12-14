<?php
// export_books.php
require_once 'config.php';
require_once 'auth_check.php';

// Check if user is authorized
if (!isAdmin() && !isLibrarian()) {
    header('Location: login.php');
    exit();
}

// Set headers based on export type
$type = isset($_GET['type']) ? $_GET['type'] : 'csv';

switch ($type) {
    case 'csv':
        exportCSV();
        break;
    case 'excel':
        exportExcel();
        break;
    case 'pdf':
        exportPDF();
        break;
    case 'json':
        exportJSON();
        break;
    default:
        exportCSV();
}

function exportCSV() {
    global $pdo;
    
    $filename = "books_export_" . date('Y-m-d') . ".csv";
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fwrite($output, "\xEF\xBB\xBF");
    
    // Column headers
    $headers = [
        'ID',
        'Title',
        'Author',
        'ISBN',
        'Publisher',
        'Publication Year',
        'Genre',
        'Description',
        'Total Copies',
        'Available Copies',
        'Location',
        'Status',
        'Created At'
    ];
    
    fputcsv($output, $headers);
    
    // Fetch books data
    $query = "SELECT 
                b.id,
                b.title,
                b.author,
                b.isbn,
                b.publisher,
                b.publication_year,
                g.name as genre,
                b.description,
                b.total_copies,
                b.available_copies,
                b.location,
                b.status,
                b.created_at
              FROM books b
              LEFT JOIN genres g ON b.genre_id = g.id
              ORDER BY b.title";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Write data rows
    foreach ($books as $book) {
        fputcsv($output, [
            $book['id'],
            $book['title'],
            $book['author'],
            $book['isbn'],
            $book['publisher'],
            $book['publication_year'],
            $book['genre'],
            strip_tags($book['description']), // Remove HTML tags
            $book['total_copies'],
            $book['available_copies'],
            $book['location'],
            $book['status'],
            $book['created_at']
        ]);
    }
    
    fclose($output);
    exit();
}

function exportExcel() {
    global $pdo;
    
    $filename = "books_export_" . date('Y-m-d') . ".xls";
    
    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Fetch books data
    $query = "SELECT 
                b.id,
                b.title,
                b.author,
                b.isbn,
                b.publisher,
                b.publication_year,
                g.name as genre,
                b.description,
                b.total_copies,
                b.available_copies,
                b.location,
                b.status,
                b.created_at
              FROM books b
              LEFT JOIN genres g ON b.genre_id = g.id
              ORDER BY b.title";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Start HTML table for Excel
    echo '<table border="1">';
    
    // Table headers
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>Title</th>';
    echo '<th>Author</th>';
    echo '<th>ISBN</th>';
    echo '<th>Publisher</th>';
    echo '<th>Publication Year</th>';
    echo '<th>Genre</th>';
    echo '<th>Description</th>';
    echo '<th>Total Copies</th>';
    echo '<th>Available Copies</th>';
    echo '<th>Location</th>';
    echo '<th>Status</th>';
    echo '<th>Created At</th>';
    echo '</tr>';
    
    // Table data
    foreach ($books as $book) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($book['id']) . '</td>';
        echo '<td>' . htmlspecialchars($book['title']) . '</td>';
        echo '<td>' . htmlspecialchars($book['author']) . '</td>';
        echo '<td>' . htmlspecialchars($book['isbn']) . '</td>';
        echo '<td>' . htmlspecialchars($book['publisher']) . '</td>';
        echo '<td>' . htmlspecialchars($book['publication_year']) . '</td>';
        echo '<td>' . htmlspecialchars($book['genre']) . '</td>';
        echo '<td>' . htmlspecialchars(strip_tags($book['description'])) . '</td>';
        echo '<td>' . htmlspecialchars($book['total_copies']) . '</td>';
        echo '<td>' . htmlspecialchars($book['available_copies']) . '</td>';
        echo '<td>' . htmlspecialchars($book['location']) . '</td>';
        echo '<td>' . htmlspecialchars($book['status']) . '</td>';
        echo '<td>' . htmlspecialchars($book['created_at']) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    exit();
}

function exportJSON() {
    global $pdo;
    
    $filename = "books_export_" . date('Y-m-d') . ".json";
    
    // Set headers for JSON download
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Fetch books data
    $query = "SELECT 
                b.id,
                b.title,
                b.author,
                b.isbn,
                b.publisher,
                b.publication_year,
                g.name as genre,
                b.description,
                b.total_copies,
                b.available_copies,
                b.location,
                b.status,
                b.created_at
              FROM books b
              LEFT JOIN genres g ON b.genre_id = g.id
              ORDER BY b.title";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Clean description field
    foreach ($books as &$book) {
        $book['description'] = strip_tags($book['description']);
    }
    
    // Output JSON
    echo json_encode($books, JSON_PRETTY_PRINT);
    exit();
}

function exportPDF() {
    // Note: This requires TCPDF, FPDF, or Dompdf library
    // Here's a basic example using FPDF (you need to install it first)
    
    require_once('fpdf/fpdf.php');
    
    global $pdo;
    
    $filename = "books_export_" . date('Y-m-d') . ".pdf";
    
    // Fetch books data
    $query = "SELECT 
                b.id,
                b.title,
                b.author,
                b.isbn,
                b.publisher,
                b.publication_year,
                g.name as genre,
                b.description,
                b.total_copies,
                b.available_copies,
                b.location,
                b.status,
                DATE(b.created_at) as created_date
              FROM books b
              LEFT JOIN genres g ON b.genre_id = g.id
              ORDER BY b.title
              LIMIT 100"; // Limit for PDF
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create PDF
    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->AddPage();
    
    // Title
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'Books Export - ' . date('Y-m-d'), 0, 1, 'C');
    $pdf->Ln(10);
    
    // Table headers
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(15, 10, 'ID', 1);
    $pdf->Cell(60, 10, 'Title', 1);
    $pdf->Cell(40, 10, 'Author', 1);
    $pdf->Cell(30, 10, 'ISBN', 1);
    $pdf->Cell(40, 10, 'Publisher', 1);
    $pdf->Cell(20, 10, 'Year', 1);
    $pdf->Cell(30, 10, 'Genre', 1);
    $pdf->Cell(20, 10, 'Copies', 1);
    $pdf->Cell(20, 10, 'Available', 1);
    $pdf->Cell(30, 10, 'Status', 1);
    $pdf->Ln();
    
    // Table data
    $pdf->SetFont('Arial', '', 8);
    foreach ($books as $book) {
        $pdf->Cell(15, 10, $book['id'], 1);
        $pdf->Cell(60, 10, substr($book['title'], 0, 30), 1);
        $pdf->Cell(40, 10, substr($book['author'], 0, 20), 1);
        $pdf->Cell(30, 10, $book['isbn'], 1);
        $pdf->Cell(40, 10, substr($book['publisher'], 0, 20), 1);
        $pdf->Cell(20, 10, $book['publication_year'], 1);
        $pdf->Cell(30, 10, substr($book['genre'], 0, 15), 1);
        $pdf->Cell(20, 10, $book['total_copies'], 1);
        $pdf->Cell(20, 10, $book['available_copies'], 1);
        $pdf->Cell(30, 10, $book['status'], 1);
        $pdf->Ln();
    }
    
    // Output PDF
    $pdf->Output('D', $filename);
    exit();
}

// Alternative: Simple export interface (if accessed directly)
if (!isset($_GET['type'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Export Books</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .container { max-width: 600px; margin: 0 auto; }
            h1 { color: #333; }
            .export-options { margin: 20px 0; }
            .export-btn { 
                display: inline-block; 
                margin: 10px 5px; 
                padding: 10px 20px; 
                background: #4CAF50; 
                color: white; 
                text-decoration: none; 
                border-radius: 4px; 
            }
            .export-btn:hover { background: #45a049; }
            .csv { background: #2196F3; }
            .excel { background: #4CAF50; }
            .pdf { background: #f44336; }
            .json { background: #ff9800; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Export Books Data</h1>
            <p>Select export format:</p>
            <div class="export-options">
                <a href="?type=csv" class="export-btn csv">Export as CSV</a>
                <a href="?type=excel" class="export-btn excel">Export as Excel</a>
                <a href="?type=pdf" class="export-btn pdf">Export as PDF</a>
                <a href="?type=json" class="export-btn json">Export as JSON</a>
            </div>
            <p><a href="books.php">← Back to Books Management</a></p>
        </div>
    </body>
    </html>
    <?php
}
?>