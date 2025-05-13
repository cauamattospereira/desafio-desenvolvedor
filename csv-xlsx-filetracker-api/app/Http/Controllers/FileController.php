<?php

namespace App\Http\Controllers;

use App\Models\File;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class FileController extends Controller
{
    /**
     * 
     */
    public function history(Request $request)
    {
        $searchOriginalFilename = $request->input('filename');
        $searchUploadDateBrasiliaLocalTime = $request->input('referenceDateBrasilia');
        $searchUploadDateUtc = $request->input('referenceDateUtc');

        $query = File::orderBy('created_at', 'desc')
            ->where('type', 'root');

        if ($searchOriginalFilename) {
            $query->where('filename', $searchOriginalFilename);
        }

        if ($searchUploadDateBrasiliaLocalTime) {
            $start = Carbon::parse($searchUploadDateBrasiliaLocalTime)->startOfDay()->setTimezone('America/Sao_Paulo')->toIso8601String();
            $end = Carbon::parse($searchUploadDateBrasiliaLocalTime)->endOfDay()->setTimezone('America/Sao_Paulo')->toIso8601String();

            $query->whereBetween('upload_date_brasilia_local_time', [$start, $end]);
        }

        if ($searchUploadDateUtc) {
            $start = Carbon::parse($searchUploadDateUtc)->startOfDay();
            $end = Carbon::parse($searchUploadDateUtc)->endOfDay();

            $query->whereBetween('created_at', [$start, $end]);
        }

        $data = $query->get()->makeHidden(['data', 'uploaded_metadata', 'processing_info']);

        return response()->json([
            'message' => 'File upload history query completed successfully',
            'data' => $data,
            'status' => 200,
            'success' => true,
        ]);
    }


    /**
     * Function for uploading a CSV/Excel file
     * 
     * This functions separate the file in chunks of 10000 lines for saving
     * on MongoDB without surpassing the limit of 16mb per document.
     */
    public function upload(Request $request)
    {
        $start = microtime(true); // will serve for calculating the time it took for complete the process

        if (!$request->hasFile('file')) {
            return response()->json([
                'error' => "No valid file is found with 'file' key",
                'message' => 'Please, upload a valid CSV or Excel file in a form data',
                'success' => false,
            ], 400);
        }

        /**
         * This variables are necessary for validate the file
         */
        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $contentHash = md5_file($file->getRealPath());

        if ($extension !== 'csv' && $extension !== 'xlsx') {
            return response()->json([
                'error' => 'Invalid file type',
                'message' => 'Only CSV and Excel files are supported.',
                'success' => false,
            ], 400);
        }

        $duplicate = File::where('content_hash', $contentHash)->first();

        if ($duplicate) {
            $duplicate->makeHidden(['data']);

            return response()->json([
                'error' => 'Duplicated file',
                'message' => 'A file with this content already exists in the database.',
                'duplicated_file' => $duplicate,
                'success' => false,
            ], 409);
        }

        $linesPerChunk = 10000;
        $chunkIndex = 1;
        $rowCount = 0;
        $data = [];
        $totalProcessingTimeMs = 0;
        $totalInvalidLines = 0;
                
        $path = $file->getRealPath();
        $documentOriginalName = $file->getClientOriginalName();
        $chunksFilename = date('Y-m-d_His') . "_[index]_" . $documentOriginalName;
        $fileName = date('Y-m-d_His') . "_{$chunkIndex}_" . $documentOriginalName;
        $documentOriginalExtension = $file->getClientOriginalExtension();
        $uploadedAtUtc = Carbon::now();
        $uploadedAtBrasilia = Carbon::now('America/Sao_Paulo')->toIso8601String();
        $fileSize = $file->getSize();
        $totalLines = $this->countCsvLines($path);
        

        $rootFile = File::create([
            'filename' => $documentOriginalName,
            'content_hash' => $contentHash,
            'upload_date_brasilia_local_time' => $uploadedAtBrasilia,
            'uploaded_metadata' => [
                'original_file_size' => $fileSize,
                'original_extension' => $documentOriginalExtension,
            ],
            'processing_info' => [
                'number_of_chunks' => 0,
                'chunk_total_lines' => $totalLines,
                'valid_lines' => 0,
            ],
            'type' => 'root',
        ]);

        $rows = [];

        if ($extension === 'csv') {
            $csvFile = fopen($path, 'r');
            
            fgetcsv($csvFile, null, ';'); // Skip first line of the file (the file for test have a 'Status do arquivo' header)
            $header = fgetcsv($csvFile, null, ';'); // Read real header

            while (($row = fgetcsv($csvFile, null, ';')) !== false) {
                $rows[] = array_combine($header, $row);
            }

            fclose($csvFile);
        }

        if ($extension === 'xlsx') {
            $collection = Excel::toCollection(null, $file)->first();

            $header = $collection->get(1)->toArray(); // skip the first header ('Status do arquivo') and get the real header
            $collection = $collection->slice(2); // start on line 2

            foreach ($collection as $row) {
                $rowArray = $row->toArray();

                if (count($rowArray) === count($header)) {
                    $rows[] = array_combine($header, $rowArray);
                }
            }
        }
        
        if ($extension === 'xlsx') {
            $collection = Excel::toCollection(null, $file)->first();

            $header = $collection->get(1)->toArray();
            $collection = $collection->slice(2); 

            foreach ($collection as $row) {
                if (count($row) === count($header)) {
                    $rows[] = array_combine($header, $row->toArray());
                }
            }
        } 

        // save remaining data not included in other chunks
        if (!empty($data)) {

            File::create([
                'filename' => $fileName,
                'original_filename' => $documentOriginalName,
                'content_hash' => $contentHash,
                'upload_date_brasilia_local_time' => $uploadedAtBrasilia,
                'uploaded_metadata' => [
                    'original_file_size' => $fileSize,
                    'original_extension' => $documentOriginalExtension,
                ],
                'processing_info' => [
                    'chunk_total_lines' => $totalLines,
                    'valid_lines' => count($data),
                ],
                'data' => $data,
                'type' => 'chunk',
                'root_id' => $rootFile->id,
            ]);
        }


        $end = microtime(true);
        $processingTimeMs = round(($end - $start) * 1000);
        $totalProcessingTimeMs += $processingTimeMs;

        /**
         * update the $rootFile entry with final data before return a response for the user
         */
        $rootFile->update([
            'processing_info' => [
                'number_of_chunks' => $chunkIndex,
                'chunk_total_lines' => $totalLines,
            ],
        ]);


        return response()->json([
            'message' => 'File uploaded and chunked successfully.',
            'content_hash' => $contentHash,
            'uploaded_metadata' => [
                'root_filename' => $documentOriginalName,
                'chunks_filename' => $chunksFilename,
                'upload_date_brasilia_local_time' => $uploadedAtBrasilia,
                'uploaded_at_utc' => $uploadedAtUtc,
                'file_size' => $fileSize,
                'extension' => $documentOriginalExtension,
            ],
            'processing_info' => [
                'chunk_total_lines' => $totalLines,
                'total_chunks' => $chunkIndex,
                'total_lines' => $totalLines,
                'total_processing_time_ms' => $totalProcessingTimeMs,
            ],
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
