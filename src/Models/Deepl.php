<?

namespace NorthLab\AutoTranslate\Models;


class Deepl extends Model
{

    protected $table = 'deepls';

    protected $guarded = [];

    protected $fillable = ['key', 'api_key'];

}
