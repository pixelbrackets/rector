<?php

declare(strict_types=1);

namespace Rector\TypeDeclaration\Tests\Rector\FunctionLike\ReturnTypeDeclarationRector\Fixture;

use Rector\TypeDeclaration\Tests\Rector\FunctionLike\ReturnTypeDeclarationRector\Source\RespectVendorReturnType;

final class RespectVendorReturnType2 extends RespectVendorReturnType
{
    public function run()
    {
       return true;
    }
}

?>
-----
<?php

declare(strict_types=1);

namespace Rector\TypeDeclaration\Tests\Rector\FunctionLike\ReturnTypeDeclarationRector\Fixture;

use Rector\TypeDeclaration\Tests\Rector\FunctionLike\ReturnTypeDeclarationRector\Source\RespectVendorReturnType;

final class RespectVendorReturnType2 extends RespectVendorReturnType
{
    public function run(): bool
    {
       return true;
    }
}

?>
