<?php

/*
 * @author Carlos García Gómez      neorazorx@gmail.com
 * @copyright 2016, Carlos García Gómez. All Rights Reserved. 
 */

require_model('cuenta.php');
require_model('ejercicio.php');
require_model('linea_libro_iva.php');
/**
 * Description of libro_iva_cron
 *
 * @author carlos
 */
class libro_iva_cron
{
   private $db;
   private $ejercicio;
   private $linea;
   
   public function __construct(&$db)
   {
      $this->db = $db;
      $this->ejercicio = new ejercicio();
      $this->linea = new linea_libro_iva();
      
      $this->generar_libros();
      $this->completar_libros();
   }
   
   private function generar_libros()
   {
      $cuenta0 = new cuenta();
      foreach($this->ejercicio->all() as $eje)
      {
         //if( $eje->abierto() )
         //{
            /// iva soportado
            $cuenta = $cuenta0->get_cuentaesp('IVASOP', $eje->codejercicio);
            if($cuenta)
            {
               $sql = "SELECT a.idasiento,p.idpartida,a.numero,a.fecha,a.importe,p.cifnif,"
                       . "p.concepto,p.debe,p.baseimponible,p.iva"
                       . " FROM co_partidas p, co_asientos a"
                       . " WHERE p.idasiento = a.idasiento"
                       . " AND p.codsubcuenta LIKE '".$cuenta->codcuenta."%' "
                       . " AND a.fecha >= ".$eje->var2str($eje->fechainicio)
                       . " AND a.fecha <= ".$eje->var2str($eje->fechafin)
                       . " ORDER BY a.numero ASC;";
               
               $data = $this->db->select($sql);
               if($data)
               {
                  $numero = 0;
                  $idasiento = FALSE;
                  foreach($data as $d)
                  {
                     if( strtolower(substr($d['concepto'], 0, 10)) != 'regulariza' )
                     {
                        if($idasiento != intval($d['idasiento']))
                        {
                           $idasiento = intval($d['idasiento']);
                           $numero++;
                        }
                        
                        $linea = $this->linea->get_by_idpartida($d['idpartida']);
                        if($linea)
                        {
                           if($linea->numero != $numero)
                           {
                              $linea->numero = $numero;
                              $linea->save();
                           }
                        }
                        else
                        {
                           $linea = new linea_libro_iva();
                           $linea->idasiento = intval($d['idasiento']);
                           $linea->idpartida = intval($d['idpartida']);
                           $linea->codejercicio = $eje->codejercicio;
                           $linea->tipo = 'IVASOP';
                           $linea->fecha = date('d-m-Y', strtotime($d['fecha']));
                           $linea->cifnif = $d['cifnif'];
                           $linea->baseimponible = floatval($d['baseimponible']);
                           $linea->iva = floatval($d['iva']);
                           $linea->totaliva = floatval($d['debe']);
                           
                           if($linea->baseimponible == 0 AND $linea->totaliva != 0)
                           {
                              //if($linea->iva == 0)
                              //{
                              //    $linea->iva = 21;
                              //}
                              
                              $linea->baseimponible = round($linea->totaliva * 100 / $linea->iva, 2);
                              if($linea->baseimponible + $linea->totaliva > $linea->importe)
                              {
                                 $linea->baseimponible = $linea->importe - $linea->totaliva;
                              }
                           }
                           
                           $linea->importe = floatval($d['importe']);
                           $linea->numero = $numero;
                           $linea->save();
                        }
                     }
                  }
               }
            }
            
            echo '.';
            
            /// iva soportado
            $cuenta = $cuenta0->get_cuentaesp('IVAREP', $eje->codejercicio);
            if($cuenta)
            {
               $sql = "SELECT a.idasiento,p.idpartida,a.numero,a.fecha,a.importe,p.cifnif,"
                       . "p.concepto,p.haber,p.baseimponible,p.iva"
                       . " FROM co_partidas p, co_asientos a"
                       . " WHERE p.idasiento = a.idasiento"
                       . " AND p.codsubcuenta LIKE '".$cuenta->codcuenta."%' "
                       . " AND a.fecha >= ".$eje->var2str($eje->fechainicio)
                       . " AND a.fecha <= ".$eje->var2str($eje->fechafin)
                       . " ORDER BY a.numero ASC;";
               
               $data = $this->db->select($sql);
               if($data)
               {
                  foreach($data as $d)
                  {
                     if( strtolower(substr($d['concepto'], 0, 10)) != 'regulariza' )
                     {
                        $linea = $this->linea->get_by_idpartida($d['idpartida']);
                        if($linea)
                        {
                           /// nada
                        }
                        else
                        {
                           $linea = new linea_libro_iva();
                           $linea->idasiento = intval($d['idasiento']);
                           $linea->idpartida = intval($d['idpartida']);
                           $linea->codejercicio = $eje->codejercicio;
                           $linea->tipo = 'IVAREP';
                           $linea->fecha = date('d-m-Y', strtotime($d['fecha']));
                           $linea->cifnif = $d['cifnif'];
                           $linea->baseimponible = floatval($d['baseimponible']);
                           $linea->iva = floatval($d['iva']);
                           $linea->totaliva = floatval($d['haber']);
                           
                           if($linea->baseimponible == 0 AND $linea->totaliva != 0)
                           {
                              //if($linea->iva == 0)
                              //{
                              //   $linea->iva = 21;
                              //}
                              
                              $linea->baseimponible = round($linea->totaliva * 100 / $linea->iva, 2);
                              if($linea->baseimponible + $linea->totaliva > $linea->importe)
                              {
                                 $linea->baseimponible = $linea->importe - $linea->totaliva;
                              }
                           }
                           
                           $linea->importe = floatval($d['importe']);
                           $linea->save();
                        }
                     }
                  }
               }
            }
            
            echo '.';
         //}
      }
   }
   
   private function completar_libros()
   {
      $offset = 0;
      $lineas = $this->linea->all($offset);
      while($lineas)
      {
         foreach($lineas as $linea)
         {
            $guardar = FALSE;
            
            if( $linea->tipo == 'IVAREP' AND is_null($linea->codserie) )
            {
               $sql = "SELECT numero,codserie FROM facturascli WHERE idasiento = ".$linea->var2str($linea->idasiento);
               
               $data = $this->db->select($sql);
               if($data)
               {
                  foreach($data as $d)
                  {
                     
                     $linea->numero = intval($d['numero']);
                     $linea->codserie = $d['codserie'];
                     $guardar = TRUE;
                     break;
                  }
               }
            }
            
            if($linea->nombre == '')
            {
               $sql = "SELECT p.codsubcuenta,s.descripcion FROM co_subcuentas s, co_partidas p"
                       . " WHERE p.idsubcuenta = s.idsubcuenta"
                       . " AND p.idasiento = ".$linea->var2str($linea->idasiento)
                       . " AND p.idpartida != ".$linea->var2str($linea->idpartida)
                       . " ORDER BY p.codsubcuenta ASC;";
               
               $data = $this->db->select($sql);
               if($data)
               {
                  foreach($data as $d)
                  {
                     $linea->nombre = $d['descripcion'];
                     $guardar = TRUE;
                     break;
                  }
               }
            }
            
            if($guardar)
            {
               $linea->save();
               echo '.';
            }
            
            $offset++;
         }
         
         $lineas = $this->linea->all($offset);
         echo '+';
      }
   }
}

new libro_iva_cron($db);