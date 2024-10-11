<?

namespace NorthLab\AutoTranslate\Contracts;


class Deepl extends Model
{

    protected $table = 'deepls';

    protected $guarded = [];

    protected $fillable = ['key', 'api_key'];

}
