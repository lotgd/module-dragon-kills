<?php
declare(strict_types=1);

namespace LotGD\Module\DragonKills\Models;

use DateTime;

use DateTimeInterface;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;

use LotGD\Core\Models\SaveableInterface;
use LotGD\Core\Models\Character;
use LotGD\Core\Tools\Model\Saveable;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Model for tracking dragon kills, including who and when they slayed the
 * horrid beast.
 * @Entity
 * @Table(name="lotgd_dragon_kills")
 */
class DragonKill implements SaveableInterface
{
    use Saveable;

    /** @Id @Column(type="uuid", unique=True) */
    private UuidInterface $id;
    /**
     * @ManyToOne(targetEntity="LotGD\Core\Models\Character", fetch="EAGER")
     * @JoinColumn(name="killer_id", referencedColumnName="id", nullable=true)
     */
    private ?Character $killer = null;
    /** @Column(type="text", nullable=false) */

    /** @Column(type="datetime", nullable=false) */
    private DateTimeInterface $killedAt;

    /** @Column(type="datetime", nullable=false) */
    private DateTimeInterface $createdAt;

    /** @var array */
    private static array $fillable = [
        "killer",
        "killedAt",
    ];

    /**
     * Construct a new dragon kill.
     *
     * @param Character $killer
     * @param DateTime $gameTime The game time at which this kill occurred.
     */
    public function __construct(Character $killer, DateTime $gameTime)
    {
        $this->id = Uuid::uuid4();
        $this->killer = $killer;
        $this->killedAt = $gameTime;
        $this->createdAt = new DateTime();
    }

    /**
     * Returns the id
     * @return int
     */
    public function getId(): UuidInterface
    {
        return $this->id;
    }

    /**
     * Returns the character who did the killing.
     * @return Character|null
     */
    public function getKiller(): ?Character
    {
        return $this->killer;
    }

    /**
     * Returns the game time at which the killing occurred.
     * @return DateTimeInterface
     */
    public function getKilledAt(): DateTimeInterface
    {
        return $this->killedAt;
    }

    /**
     * Returns the datetime this message was created at
     * @return DateTimeInterface
     */
    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }
}
