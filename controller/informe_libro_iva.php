<?php

/*
 * @author Carlos García Gómez      neorazorx@gmail.com
 * @copyright 2015-2016, Carlos García Gómez. All Rights Reserved. 
 */

require_model('ejercicio.php');
require_model('factura_cliente.php');
require_model('linea_libro_iva.php');
require_once 'plugins/facturacion_base/extras/fs_pdf.php';

class informe_libro_iva extends fs_controller
{
   private $ejercicio;
   private $factura_cli;
   private $linea;
   
   public $ejercicios;
   public $url_recarga;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Libros de '.FS_IVA, 'informes', FALSE, TRUE);
   }
   
   protected function private_core()
   {
      $this->url_recarga = '';
      $this->factura_cli = new factura_cliente();
      
      $this->linea = new linea_libro_iva();
      $codejercicios = $this->linea->all_codejercicios();
      $this->ejercicio = new ejercicio();
      $this->ejercicios = array();
      foreach($this->ejercicio->all() as $eje)
      {
         if( in_array($eje->codejercicio, $codejercicios) )
         {
            $this->ejercicios[] = $eje;
         }
      }
      
      if( isset($_POST['codejercicio']) )
      {
         if($_POST['tipo'] == 'IVAREP')
         {
            $this->libro_iva_rep();
         }
         else
         {
            $this->libro_iva_sop();
         }
      }
   }
   
   private function libro_iva_rep()
   {
      /// desactivamos el motor de plantillas
      $this->template = FALSE;
      
      $pdf_doc = new fs_pdf('a4', 'landscape', 'Courier');
      $pdf_doc->pdf->addInfo('Title', 'Libro registro de facturas emitidas de ' . $this->empresa->nombre);
      $pdf_doc->pdf->addInfo('Subject', 'Libro registro de facturas emitidas de ' . $this->empresa->nombre);
      $pdf_doc->pdf->addInfo('Author', $this->empresa->nombre);
      $pdf_doc->pdf->ezStartPageNumbers(800, 10, 10, 'left', '{PAGENUM} de {TOTALPAGENUM}');
      
      $data = $this->linea->search($_POST['codejercicio'], 'IVAREP', $_POST['desde'], $_POST['hasta']);
      if($data)
      {
         $lineasfact = count($data);
         $linea_actual = 0;
         $lppag = 33;
         
         $total_importe = 0;
         $total_base = 0;
         $total_totaliva = 0;
         
         // Imprimimos las páginas necesarias
         while($linea_actual < $lineasfact)
         {
            /// salto de página
            if($linea_actual > 0)
            {
               $pdf_doc->pdf->ezNewPage();
            }
            
            /// Creamos la tabla del encabezado
            $pdf_doc->new_table();
            $pdf_doc->add_table_row(
                    array(
                         'dato1' => "<b>Documento:</b>\n<b>Empresa:</b>",
                         'valor1' => "LIBRO REGISTRO DE FACTURAS EMITIDAS\n".$this->empresa->nombre,
                         'dato2' => "<b>Fecha inicial:</b>\n<b>Fecha final:</b>",
                         'valor2' => $_POST['desde']."\n".$_POST['hasta']
                    )
            );
            $pdf_doc->save_table(
                    array(
                         'cols' => array(
                             'dato1' => array('justification' => 'right'),
                             'valo1' => array('justification' => 'left'),
                             'dato2' => array('justification' => 'right'),
                             'valo2' => array('justification' => 'left')
                         ),
                         'showLines' => 0,
                         'width' => 800
                    )
            );
            $pdf_doc->pdf->ezText("\n", 10);
            
            /// Creamos la tabla con las lineas
            $pdf_doc->new_table();
            $pdf_doc->add_table_header(
                    array(
                         'fecha' => '<b>Fecha</b>',
                         'num' => '<b>Nº</b>',
                         'serie' => '<b>Serie</b>',
                         'cifnif' => '<b>'.FS_CIFNIF.'</b>',
                         'concepto' => '<b>Cliente</b>',
                         'importe' => '<b>Importe</b>',
                         'base' => '<b>Base imp.</b>',
                         'iva' => '<b>%'.FS_IVA.'</b>',
                         'totaliva' => '<b>Cuota '.FS_IVA.'</b>'
                    )
            );
            for($i = $linea_actual; (($linea_actual < ($lppag + $i)) AND ($linea_actual < $lineasfact));)
            {
               $linea = $data[$linea_actual];
               $pdf_doc->add_table_row(
                           array(
                               'fecha' => $linea->fecha,
                               'num' => $linea->numero,
                               'serie' => $linea->codserie,
                               'cifnif' => $linea->cifnif,
                               'concepto' => $linea->nombre,
                               'importe' => $this->show_numero($linea->importe),
                               'base' => $this->show_numero($linea->baseimponible),
                               'iva' => $this->show_numero($linea->iva),
                               'totaliva' => $this->show_numero($linea->totaliva),
                           )
               );
               
               $total_base += $linea->baseimponible;
               $total_totaliva += $linea->totaliva;
               $total_importe = $total_base + $total_totaliva;
               
               $linea_actual++;
            }
            
            /// añadimos las sumas de la línea actual
            $pdf_doc->add_table_row(
                    array(
                            'fecha' => '',
                            'num' => '',
                            'serie' => '',
                            'cifnif' => '',
                            'concepto' => '',
                            'importe' => '<b>'.$this->show_numero($total_importe).'</b>',
                            'base' => '<b>'.$this->show_numero($total_base).'</b>',
                            'iva' => '',
                            'totaliva' => '<b>'.$this->show_numero($total_totaliva).'</b>'
                    )
            );
            $pdf_doc->save_table(
                    array(
                         'fontSize' => 8,
                         'cols' => array(
                             'importe' => array('justification' => 'right'),
                             'base' => array('justification' => 'right'),
                             'iva' => array('justification' => 'right'),
                             'totaliva' => array('justification' => 'right')
                         ),
                         'width' => 800,
                         'shaded' => 0
                    )
            );
         }
         
         $this->template = FALSE;
         $pdf_doc->show();
      }
      else
      {
         $this->new_error_msg('Sin datos.');
      }
   }
   
   private function libro_iva_sop()
   {
      $pdf_doc = new fs_pdf('a4', 'landscape', 'Courier');
      $pdf_doc->pdf->addInfo('Title', 'Libro registro de facturas recibidas de ' . $this->empresa->nombre);
      $pdf_doc->pdf->addInfo('Subject', 'Libro registro de facturas recibidas de ' . $this->empresa->nombre);
      $pdf_doc->pdf->addInfo('Author', $this->empresa->nombre);
      $pdf_doc->pdf->ezStartPageNumbers(800, 10, 10, 'left', '{PAGENUM} de {TOTALPAGENUM}');
      
      $data = $this->linea->search($_POST['codejercicio'], 'IVASOP', $_POST['desde'], $_POST['hasta']);
      if($data)
      {
         $lineasfact = count($data);
         $linea_actual = 0;
         $lppag = 33;
         
         $total_importe = 0;
         $total_base = 0;
         $total_totaliva = 0;
         
         // Imprimimos las páginas necesarias
         while($linea_actual < $lineasfact)
         {
            /// salto de página
            if($linea_actual > 0)
            {
               $pdf_doc->pdf->ezNewPage();
            }
            
            /// Creamos la tabla del encabezado
            $pdf_doc->new_table();
            $pdf_doc->add_table_row(
                    array(
                         'dato1' => "<b>Documento:</b>\n<b>Empresa:</b>",
                         'valor1' => "LIBRO REGISTRO DE FACTURAS RECIBIDAS\n".$this->empresa->nombre,
                         'dato2' => "<b>Fecha inicial:</b>\n<b>Fecha final:</b>",
                         'valor2' => $_POST['desde']."\n".$_POST['hasta']
                    )
            );
            $pdf_doc->save_table(
                    array(
                         'cols' => array(
                             'dato1' => array('justification' => 'right'),
                             'valo1' => array('justification' => 'left'),
                             'dato2' => array('justification' => 'right'),
                             'valo2' => array('justification' => 'left')
                         ),
                         'showLines' => 0,
                         'width' => 800
                    )
            );
            $pdf_doc->pdf->ezText("\n", 10);
            
            /// Creamos la tabla con las lineas
            $pdf_doc->new_table();
            $pdf_doc->add_table_header(
                    array(
                         'fecha' => '<b>Fecha</b>',
                         'num' => '<b>Registro</b>',
                         'cifnif' => '<b>'.FS_CIFNIF.'</b>',
                         'concepto' => '<b>Proveedor</b>',
                         'importe' => '<b>Importe</b>',
                         'base' => '<b>Base imp.</b>',
                         'iva' => '<b>%'.FS_IVA.'</b>',
                         'totaliva' => '<b>Cuota '.FS_IVA.'</b>'
                    )
            );
            for($i = $linea_actual; (($linea_actual < ($lppag + $i)) AND ($linea_actual < $lineasfact));)
            {
               $linea = $data[$linea_actual];
               $pdf_doc->add_table_row(
                           array(
                               'fecha' => $linea->fecha,
                               'num' => $linea->numero,
                               'cifnif' => $linea->cifnif,
                               'concepto' => $linea->nombre,
                               'importe' => $this->show_numero($linea->importe),
                               'base' => $this->show_numero($linea->baseimponible),
                               'iva' => $this->show_numero($linea->iva),
                               'totaliva' => $this->show_numero($linea->totaliva),
                           )
               );
               
               $total_base += $linea->baseimponible;
               $total_totaliva += $linea->totaliva;
               $total_importe = $total_base + $total_totaliva;
               
               $linea_actual++;
            }
            
            /// añadimos las sumas de la línea actual
            $pdf_doc->add_table_row(
                    array(
                            'fecha' => '',
                            'num' => '',
                            'cifnif' => '',
                            'concepto' => '',
                            'importe' => '<b>'.$this->show_numero($total_importe).'</b>',
                            'base' => '<b>'.$this->show_numero($total_base).'</b>',
                            'iva' => '',
                            'totaliva' => '<b>'.$this->show_numero($total_totaliva).'</b>'
                    )
            );
            $pdf_doc->save_table(
                    array(
                         'fontSize' => 8,
                         'cols' => array(
                             'importe' => array('justification' => 'right'),
                             'base' => array('justification' => 'right'),
                             'iva' => array('justification' => 'right'),
                             'totaliva' => array('justification' => 'right')
                         ),
                         'width' => 800,
                         'shaded' => 0
                    )
            );
         }
         
         $this->template = FALSE;
         $pdf_doc->show();
      }
      else
      {
         $this->new_error_msg('Sin datos.');
      }
   }
}
