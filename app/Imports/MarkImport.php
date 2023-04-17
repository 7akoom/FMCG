<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToModel;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use DB;

class MarkImport implements ToCollection
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function Collection(Collection $rows)
{
    $i = 0;
    foreach ($rows as $row)
    {
        $spreadsheet = IOFactory::load(request()->file('file'));
        $j = 0;
        foreach ($spreadsheet->getActiveSheet()->getDrawingCollection() as $drawing)
        {
            if ($j == $i)
            {
                if ($drawing instanceof MemoryDrawing)
                {
                    ob_start();
                    call_user_func(
                        $drawing->getRenderingFunction(),
                        $drawing->getImageResource()
                    );
                    $imageContents = ob_get_contents();
                    ob_end_clean();
                    switch ($drawing->getMimeType())
                    {
                        case MemoryDrawing::MIMETYPE_PNG:
                            $extension = 'png';
                            break;
                        case MemoryDrawing::MIMETYPE_GIF:
                            $extension = 'gif';
                            break;
                        case MemoryDrawing::MIMETYPE_JPEG:
                            $extension = 'jpg';
                            break;
                    }
                }
                else
                {
                    $zipReader = fopen($drawing->getPath(), 'r');
                    $imageContents = '';
                    while (!feof($zipReader))
                    {
                        $imageContents .= fread($zipReader, 1024);
                    }
                    fclose($zipReader);
                    $extension = $drawing->getExtension();
                }
                $file_name = Carbon::now()->micro.'_'.strtolower(str_replace(' ', '_', $row['0'])).'_'.Carbon::now()->micro. '.' . $extension;
                Storage::disk('local')->put('public/media/mark/'. $file_name,$imageContents);
                break;
            }
            $j++;
        }
        DB::table('lg_325_mark')->insert([
            'code' => $row['0'],
            'specode' => $row['1'],
            'descr' => 'public/media/mark/'.$file_name,
        ]);
        $i++;
    }
}
}
