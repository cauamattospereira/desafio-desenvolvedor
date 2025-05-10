<?php

namespace App\Http\Controllers;

use App\Models\File;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use SebastianBergmann\CodeCoverage\Report\Xml\Totals;

class FileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Function for uploading a CSV/Excel file
     */
    public function upload(Request $request)
    {
        if ($request->hasFile('file') == false) {
            return response()->json(
                [
                    'error' => "No valid file is found with 'file' key",
                    'message' => 'Please, upload a valid CSV or Excel file in a form data'
                ],
                400
            );
        }

        $file = $request->file('file');

        $path = $file->getRealPath();
        $csvFile = fopen($path, 'r');

        $currentRow = 0;
        $page = $request->input('page', 1);
        $perPage = 100;
        $offset = ($page - 1) * $perPage;
        $data = [];

        /**
         *  fgetcsv is called here to move the internal pointer to the next line and
         *  desconsider the first line of the csv with 'Status do Arquivo: Final'
         *  content
         * */
        fgetcsv($csvFile, null, ';');

        /**
         * get header and row content of the current line of the csv/excel file
         */
        $header = fgetcsv($csvFile, null, ';');
        $row = fgetcsv($csvFile, null, ';');

        /**
         * feed the $data variable with the content of the csv/excel file
         */
        while ($currentRow < 100000) {
            $data[] = array_combine($header, $row);
            $currentRow++;
        }

        fclose($csvFile);

        $documentOriginalName = $file->getClientOriginalName();
        $newFileName = date('Y-h-d_His') . '_' . $file->getClientOriginalName();
        $documentOriginalExtension = $file->getClientOriginalExtension();
        $uploadedAt = Carbon::now();
        $fileSize = $file->getSize();
        $totalLines = $this->countCsvLines($path);
        $dataLines = $totalLines - 2;
        $itemsPerPage = (int) ($request->get('per_page', $perPage));
        $totalPages = ceil($dataLines / $itemsPerPage);

        $storedFile = [
            'uploaded_metadata' => [
                "original_filename" => $documentOriginalName,
                "stored_as" => $newFileName,
                "uploaded_at" => $uploadedAt,
                "file_size" => $fileSize,
                "extension" => $documentOriginalExtension
            ],
            'processing_info' => [
                'total_lines' => $totalLines,
                'valid_lines' => $dataLines,
                'invalid_lines' => '0 [FAKE]',
                'processing_time_ms' => '1532 [FAKE]'
            ],
            'data' => $data,
        ];

        File::create($storedFile);

        return response()->json([
            'message' => 'File uploaded successfully',
            'data' => $storedFile,
            'status' => 201,
            'success' => true,
        ], 201);
    }

    // #TODO move this function to a helper folder
    private function countCsvLines($path)
    {
        $lineCount = 0;
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return 0;
        }

        while (!feof($handle)) {
            $line = fread($handle, 8192);
            $lineCount += substr_count($line, "\n");
        }

        fclose($handle);
        return $lineCount;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(File $file)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(File $file)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, File $file)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(File $file)
    {
        //
    }
}
