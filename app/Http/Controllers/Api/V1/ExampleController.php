<?php

namespace App\Http\Controllers\Api\V1;

use App\Excel\ExcelImport;
use App\Http\Controllers\Controller;
use App\Models\TableA;
use App\Models\TableB;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;


class ExampleController extends Controller
{
    public function handle(Request $request)
    {
        // 1. Check Exist File
        if (!$request->hasFile('excel_a')) {
            return response()->json(['error' => 'The required Excel file is missing.'], 422);
        }

        // 2. Parse Excel File
        $excelA = $request->file('excel_a');

        // 3. Change Data To Array
        $dataA = Excel::toArray([], $excelA);

        $batchSize = 100; // Adjust this based on your server's capabilities
        $countSimilar = 0; // count of similar row between table a and b

        // 4. Process the data in batches For Table A and 700 rows similar for table B
        foreach (array_chunk($dataA[0], $batchSize) as $batch) {
            $upsertData = [];

            foreach ($batch as $row) {
                $upsertData[] = [
                    'phone' => $row[0]
                ];
            }

            TableA::upsert($upsertData, ['phone'], ['phone']);

            if ($countSimilar < 700) {
                $upsertDataB = [];
                foreach ($batch as $row) {
                    $upsertDataB[] = [
                        'phone' => $row[0],
                        'product' => $this->generateRandomProduct()
                    ];
                }

                TableB::upsert($upsertDataB, ['phone', 'product'], ['phone', 'product']);
                $countSimilar += $batchSize;
            }
        }

        // 5. Create 300 rows randomly for Table B
        $countRandomRows = 300;

        for ($i = 0; $i < $countRandomRows; $i++) {
            $randomPhone = $this->generateRandomPhoneNumber();
            $randomProduct = $this->generateRandomProduct();

            TableB::upsert([
                [
                    'phone' => $randomPhone,
                    'product' => $randomProduct
                ]
            ], ['phone', 'product'], ['phone', 'product']);
        }

        // 7. Get the count of rows with similar phone numbers in Table A and B
        $countCommonProductA = DB::table('table_b_s')
            ->join('table_a_s', 'table_b_s.phone', '=', 'table_a_s.phone')
            ->where('table_b_s.product', 'a')
            ->count();

        // 8. Calculate the count of rows in Table B with product 'b' and have a common phone number with Table A
        $countCommonProductB = DB::table('table_b_s')
            ->join('table_a_s', 'table_b_s.phone', '=', 'table_a_s.phone')
            ->where('table_b_s.product', 'b')
            ->count();

        return response()->json([
            'message' => 'Upsert operation completed successfully.',
            'countCommonProductA' => $countCommonProductA,
            'countCommonProductB' => $countCommonProductB,
        ], 201);
    }

    private function generateRandomPhoneNumber()
    {
        $phoneNumber = '0';
        for ($i = 1; $i <= 9; $i++) {
            $phoneNumber .= rand(0, 9);
        }
        return $phoneNumber;
    }

    private function generateRandomProduct()
    {
        return rand(1, 2) === 1 ? 'a' : 'b';
    }


}
