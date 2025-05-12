<?php

namespace App\Http\Controllers;

use App\Models\File;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FileController extends Controller
{
    /**
     * 
     */
    public function history(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $searchOriginalFilename = $request->input('filename', null);
        $searchUploadDateBrasiliaLocalTime = $request->input('uploadDateBrasilia', null);
        $searchUploadDateUtc = $request->input('uploadDateUtc', null);

        if ($searchOriginalFilename !== null) {
        }

        $paginated = File::orderBy('created_at', 'desc')
            ->where('type', 'root')
            ->paginate($perPage)
            ->through(fn($item) => $item->makeHidden(['data', 'uploaded_metadata', 'processing_info']));

        return response()->json([
            'message' => 'File upload history query completed successfully',
            'items' => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'next_page_url' => $paginated->nextPageUrl(),
                'prev_page_url' => $paginated->previousPageUrl(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
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

        $file = $request->file('file');
        $contentHash = md5_file($file->getRealPath());

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

        $path = $file->getRealPath();
        $csvFile = fopen($path, 'r');

        fgetcsv($csvFile, null, ';'); // Skip first line of the file (the file for test have a 'Status do arquivo' header)
        $header = fgetcsv($csvFile, null, ';'); // Read real header

        $linesPerChunk = 10000;
        $chunkIndex = 1;
        $rowCount = 0;
        $data = [];
        $invalidLines = 0;
        $totalProcessingTimeMs = 0;
        $totalInvalidLines = 0;

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
                'invalid_lines' => 0,
            ],
            'type' => 'root',
        ]);

        while (($row = fgetcsv($csvFile, null, ';')) !== false) {
            if (array_combine($header, $row) === false) {
                $invalidLines += 1;
            }

            $data[] = array_combine($header, $row);

            $rowCount++;

            if ($rowCount % $linesPerChunk === 0) {
                /**
                 * logic for calculation the time it took for process the file
                 */
                $totalInvalidLines = $invalidLines;


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
                        'invalid_lines' => $invalidLines,
                    ],
                    'data' => $data,
                    'type' => 'chunk',
                    'root_id' => $rootFile->id,
                ]);

                // prepare for next iteration
                $chunkIndex++;
                $invalidLines = 0;
                $data = [];
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
                    'invalid_lines' => $invalidLines,
                ],
                'data' => $data,
                'type' => 'chunk',
                'root_id' => $rootFile->id,
            ]);
        }

        fclose($csvFile);

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
                'total_valid_lines' => ($totalLines - $invalidLines),
                'total_invalid_lines' => $totalInvalidLines,
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
                'total_valid_lines' => ($totalLines - $invalidLines),
                'total_invalid_lines' => $totalInvalidLines,
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
