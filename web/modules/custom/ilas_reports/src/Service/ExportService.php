<?php

namespace Drupal\ilas_reports\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Render\RendererInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

/**
 * Service for exporting reports.
 */
class ExportService {

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs an ExportService.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    FileSystemInterface $file_system,
    RendererInterface $renderer
  ) {
    $this->logger = $logger_factory->get('ilas_reports');
    $this->fileSystem = $file_system;
    $this->renderer = $renderer;
  }

  /**
   * Export report as Excel.
   */
  public function exportExcel($report) {
    try {
      $spreadsheet = new Spreadsheet();
      $sheet = $spreadsheet->getActiveSheet();
      
      // Add title
      $sheet->setCellValue('A1', $report['title']);
      $sheet->mergeCells('A1:F1');
      $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
      
      // Add description
      $sheet->setCellValue('A2', $report['description']);
      $sheet->mergeCells('A2:F2');
      
      // Add generated date
      $sheet->setCellValue('A3', 'Generated: ' . date('F j, Y g:i a', $report['generated_date']));
      $sheet->mergeCells('A3:F3');
      
      $row = 5;
      
      // Add summary if available
      if (!empty($report['summary']['key_metrics'])) {
        $sheet->setCellValue('A' . $row, 'Key Metrics');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        
        foreach ($report['summary']['key_metrics'] as $label => $value) {
          $sheet->setCellValue('A' . $row, $label);
          $sheet->setCellValue('B' . $row, $value);
          $row++;
        }
        
        $row += 2;
      }
      
      // Add data tables
      if (!empty($report['data'])) {
        foreach ($report['data'] as $section => $data) {
          if (is_array($data) && !empty($data)) {
            // Section title
            $sheet->setCellValue('A' . $row, ucfirst(str_replace('_', ' ', $section)));
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $row++;
            
            // Determine if data is tabular
            if ($this->isTabularData($data)) {
              // Add headers
              $headers = array_keys(reset($data));
              $col = 'A';
              foreach ($headers as $header) {
                $sheet->setCellValue($col . $row, ucfirst(str_replace('_', ' ', $header)));
                $sheet->getStyle($col . $row)->getFont()->setBold(true);
                $col++;
              }
              $row++;
              
              // Add data rows
              foreach ($data as $item) {
                $col = 'A';
                foreach ($item as $value) {
                  $sheet->setCellValue($col . $row, $value);
                  $col++;
                }
                $row++;
              }
            }
            else {
              // Simple key-value pairs
              foreach ($data as $key => $value) {
                $sheet->setCellValue('A' . $row, ucfirst(str_replace('_', ' ', $key)));
                $sheet->setCellValue('B' . $row, $value);
                $row++;
              }
            }
            
            $row += 2;
          }
        }
      }
      
      // Auto-size columns
      foreach (range('A', 'F') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
      }
      
      // Save file
      $filename = 'report_' . $report['type'] . '_' . date('Y-m-d_His') . '.xlsx';
      $directory = 'private://reports/exports';
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
      
      $filepath = $directory . '/' . $filename;
      $writer = new Xlsx($spreadsheet);
      $writer->save($this->fileSystem->realpath($filepath));
      
      return $filepath;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to export Excel: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      throw $e;
    }
  }

  /**
   * Export report as CSV.
   */
  public function exportCsv($report) {
    try {
      $output = [];
      
      // Add header rows
      $output[] = [$report['title']];
      $output[] = [$report['description']];
      $output[] = ['Generated: ' . date('F j, Y g:i a', $report['generated_date'])];
      $output[] = [];
      
      // Add summary
      if (!empty($report['summary']['key_metrics'])) {
        $output[] = ['Key Metrics'];
        foreach ($report['summary']['key_metrics'] as $label => $value) {
          $output[] = [$label, $value];
        }
        $output[] = [];
      }
      
      // Add data
      if (!empty($report['data'])) {
        foreach ($report['data'] as $section => $data) {
          if (is_array($data) && !empty($data)) {
            $output[] = [ucfirst(str_replace('_', ' ', $section))];
            
            if ($this->isTabularData($data)) {
              // Add headers
              $output[] = array_keys(reset($data));
              
              // Add data rows
              foreach ($data as $row) {
                $output[] = array_values($row);
              }
            }
            else {
              // Simple key-value pairs
              foreach ($data as $key => $value) {
                $output[] = [ucfirst(str_replace('_', ' ', $key)), $value];
              }
            }
            
            $output[] = [];
          }
        }
      }
      
      // Write to file
      $filename = 'report_' . $report['type'] . '_' . date('Y-m-d_His') . '.csv';
      $directory = 'private://reports/exports';
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
      
      $filepath = $directory . '/' . $filename;
      $fp = fopen($filepath, 'w');
      
      foreach ($output as $row) {
        fputcsv($fp, $row);
      }
      
      fclose($fp);
      
      return $filepath;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to export CSV: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      throw $e;
    }
  }

  /**
   * Check if data is tabular.
   */
  protected function isTabularData($data) {
    if (empty($data) || !is_array($data)) {
      return FALSE;
    }
    
    $first = reset($data);
    
    // Check if first element is an array with consistent keys
    if (is_array($first)) {
      $keys = array_keys($first);
      
      // Check if all elements have the same keys
      foreach ($data as $item) {
        if (!is_array($item) || array_keys($item) !== $keys) {
          return FALSE;
        }
      }
      
      return TRUE;
    }
    
    return FALSE;
  }

  /**
   * Export data as JSON.
   */
  public function exportJson($report) {
    try {
      $json = json_encode($report, JSON_PRETTY_PRINT);
      
      $filename = 'report_' . $report['type'] . '_' . date('Y-m-d_His') . '.json';
      $directory = 'private://reports/exports';
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
      
      $filepath = $directory . '/' . $filename;
      file_put_contents($filepath, $json);
      
      return $filepath;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to export JSON: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      throw $e;
    }
  }
}