<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Category;
use App\Entity\Vault;
use App\Entity\VaultItem;
use PHPUnit\Framework\TestCase;

class VaultItemTest extends TestCase
{
    private function createValidItem(): VaultItem
    {
        $vault = new Vault();
        $item = new VaultItem();
        $item->setVault($vault);
        $item->setEncryptedData('encrypted_blob_data');
        $item->setIv(base64_encode('123456789012'));
        $item->setAuthTag(base64_encode('1234567890123456'));
        $item->setItemType(VaultItem::TYPE_LOGIN);
        $item->setTitleHint('Test Login');

        return $item;
    }

    public function testDefaultValues(): void
    {
        $item = new VaultItem();
        // isFavorite должен быть false по умолчанию
        $this->assertFalse($item->isFavorite());
        $this->assertNull($item->getCategory());
        $this->assertNull($item->getId());
    }

    public function testSetAndGetVault(): void
    {
        $item = new VaultItem();
        $vault = new Vault();
        $item->setVault($vault);

        $this->assertSame($vault, $item->getVault());
    }

    public function testSetAndGetEncryptedData(): void
    {
        $item = $this->createValidItem();
        $this->assertEquals('encrypted_blob_data', $item->getEncryptedData());
    }

    public function testSetAndGetIv(): void
    {
        $item = new VaultItem();
        $iv = base64_encode('123456789012');
        $item->setIv($iv);

        $this->assertEquals($iv, $item->getIv());
    }

    public function testSetAndGetAuthTag(): void
    {
        $item = new VaultItem();
        $tag = base64_encode('1234567890123456');
        $item->setAuthTag($tag);

        $this->assertEquals($tag, $item->getAuthTag());
    }

    public function testSetAndGetItemType(): void
    {
        $item = new VaultItem();
        $item->setItemType(VaultItem::TYPE_NOTE);

        $this->assertEquals('note', $item->getItemType());
    }

    public function testItemTypeConstants(): void
    {
        $this->assertEquals('login', VaultItem::TYPE_LOGIN);
        $this->assertEquals('note', VaultItem::TYPE_NOTE);
        $this->assertEquals('card', VaultItem::TYPE_CARD);
        $this->assertEquals('identity', VaultItem::TYPE_IDENTITY);
    }

    public function testSetAndGetTitleHint(): void
    {
        $item = new VaultItem();
        $item->setTitleHint('My Bank Account');

        $this->assertEquals('My Bank Account', $item->getTitleHint());
    }

    public function testToggleIsFavorite(): void
    {
        $item = new VaultItem();
        $this->assertFalse($item->isFavorite());

        $item->setIsFavorite(true);
        $this->assertTrue($item->isFavorite());

        $item->setIsFavorite(false);
        $this->assertFalse($item->isFavorite());
    }

    public function testSetAndGetCategory(): void
    {
        $item = new VaultItem();
        $category = new Category();
        $item->setCategory($category);

        $this->assertSame($category, $item->getCategory());
    }

    public function testSetCategoryToNull(): void
    {
        $item = new VaultItem();
        $category = new Category();
        $item->setCategory($category);
        $item->setCategory(null);

        $this->assertNull($item->getCategory());
    }
}
