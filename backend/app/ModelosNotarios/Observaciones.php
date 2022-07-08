<?php

namespace App\ModelosNotarios;

use Illuminate\Database\Eloquent\Model;

class Observaciones extends Model
{
  protected $table="Padr_onCatastralTramitesISAINotariosObservaciones";

  protected $fillable = ['id','IdDocumento','Observacion','Origen','EstatusTercero','FechaTercero','EstatusCatastro','IdCatalogoDocumento','IdTramite'];

  public $timestamps = false;
}
