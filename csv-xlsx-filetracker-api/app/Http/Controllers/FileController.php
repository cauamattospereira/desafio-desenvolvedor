<?php

namespace App\Http\Controllers;

use App\Models\File;
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
     * Function for uploading an object
     */
    public function upload(Request $request)
    {
        // $request->validate([
        //     'file' => 'required|file|mimes:csv,xlsx|max:102400'
        // ]);

        $file = $request->file('file');

        if ($file) {
            $path = $file->getRealPath();
            $csvFile = fopen($path, 'r');

            $data = [];

            /**
             *  fgetcsv is called here to move the internal pointer to the next line and
             *  desconsider the first line of the csv with 'Status do Arquivo: Final'
             *  content
             * */
            fgetcsv($csvFile, null, ';');

            $header = fgetcsv($csvFile, null, ';');

            $row = fgetcsv($csvFile, null, ';');

            $currentRow = 0;
            $page = $request->input('page', 1);
            $perPage = 10;
            // #TODO add $page value
            $offset = ($page - 1) * $perPage;


            while ($row !== false) {
                if ($currentRow >= $offset && $currentRow < $offset + $perPage) {
                    $data[] = array_combine($header, $row);
                }

                $currentRow++;

                if ($currentRow >= $offset + $perPage) {
                    break;
                }
            }

            fclose($csvFile);


            $totalLines = $this->countCsvLines($path);
            $dataLines = $totalLines - 2;

            $itemsPerPage = (int) ($request->get('per_page', $perPage));
            $totalPages = ceil($dataLines / $itemsPerPage);


            return response()->json([
                'message' => 'File uploaded successfully',
                'data' => $data,
                'page' => $page,
                'perPage' => $itemsPerPage,
                'totalPages' => $totalPages,
            ]);
        }

        return response()->json(['error' => 'No file uploaded'], 400);
    }

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
