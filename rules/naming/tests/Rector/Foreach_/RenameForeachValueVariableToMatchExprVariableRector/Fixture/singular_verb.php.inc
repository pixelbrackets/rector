<?php

namespace Rector\Naming\Tests\Rector\Foreach_\RenameForeachValueVariableToMatchExprVariableRector\Fixture;

class SingularVerb
{
    public function run()
    {
        $array = [];
        foreach ($news as $property) {
            $array[] = $property;
        }
    }
}

?>
-----
<?php

namespace Rector\Naming\Tests\Rector\Foreach_\RenameForeachValueVariableToMatchExprVariableRector\Fixture;

class SingularVerb
{
    public function run()
    {
        $array = [];
        foreach ($news as $new) {
            $array[] = $new;
        }
    }
}

?>
