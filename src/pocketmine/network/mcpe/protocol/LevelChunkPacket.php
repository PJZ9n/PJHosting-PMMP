<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use pocketmine\utils\Binary;

use function count;
use pocketmine\network\mcpe\NetworkSession;

class LevelChunkPacket extends DataPacket/* implements ClientboundPacket*/{
	public const NETWORK_ID = ProtocolInfo::LEVEL_CHUNK_PACKET;

	/** @var int */
	private $chunkX;
	/** @var int */
	private $chunkZ;
	/** @var int */
	private $subChunkCount;
	/** @var bool */
	private $cacheEnabled;
	/** @var int[] */
	private $usedBlobHashes = [];
	/** @var string */
	private $extraPayload;

	public static function withoutCache(int $chunkX, int $chunkZ, int $subChunkCount, string $payload) : self{
		$result = new self;
		$result->chunkX = $chunkX;
		$result->chunkZ = $chunkZ;
		$result->subChunkCount = $subChunkCount;
		$result->extraPayload = $payload;

		$result->cacheEnabled = false;

		return $result;
	}

	public static function withCache(int $chunkX, int $chunkZ, int $subChunkCount, array $usedBlobHashes, string $extraPayload) : self{
		(static function(int ...$hashes){})($usedBlobHashes);
		$result = new self;
		$result->chunkX = $chunkX;
		$result->chunkZ = $chunkZ;
		$result->subChunkCount = $subChunkCount;
		$result->extraPayload = $extraPayload;

		$result->cacheEnabled = true;
		$result->usedBlobHashes = $usedBlobHashes;

		return $result;
	}

	/**
	 * @return int
	 */
	public function getChunkX() : int{
		return $this->chunkX;
	}

	/**
	 * @return int
	 */
	public function getChunkZ() : int{
		return $this->chunkZ;
	}

	/**
	 * @return int
	 */
	public function getSubChunkCount() : int{
		return $this->subChunkCount;
	}

	/**
	 * @return bool
	 */
	public function isCacheEnabled() : bool{
		return $this->cacheEnabled;
	}

	/**
	 * @return int[]
	 */
	public function getUsedBlobHashes() : array{
		return $this->usedBlobHashes;
	}

	/**
	 * @return string
	 */
	public function getExtraPayload() : string{
		return $this->extraPayload;
	}

	protected function decodePayload() : void{
		$this->chunkX = $this->getVarInt();
		$this->chunkZ = $this->getVarInt();
		$this->subChunkCount = $this->getUnsignedVarInt();
		$this->cacheEnabled = (($this->get(1) !== "\x00"));
		if($this->cacheEnabled){
			for($i =  0, $count = $this->getUnsignedVarInt(); $i < $count; ++$i){
				$this->usedBlobHashes[] = (Binary::readLLong($this->get(8)));
			}
		}
		$this->extraPayload = $this->getString();
	}

	protected function encodePayload() : void{
		$this->putVarInt($this->chunkX);
		$this->putVarInt($this->chunkZ);
		$this->putUnsignedVarInt($this->subChunkCount);
		($this->buffer .= ($this->cacheEnabled ? "\x01" : "\x00"));
		if($this->cacheEnabled){
			$this->putUnsignedVarInt(count($this->usedBlobHashes));
			foreach($this->usedBlobHashes as $hash){
				($this->buffer .= (\pack("VV", $hash & 0xFFFFFFFF, $hash >> 32)));
			}
		}
		$this->putString($this->extraPayload);
	}

	public function handle(NetworkSession $handler) : bool{
		return $handler->handleLevelChunk($this);
	}
}
