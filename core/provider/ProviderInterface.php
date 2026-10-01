<?php

declare(strict_types=1);

namespace mara\core\provider;

interface ProviderInterface
{
	/**
	 * A provider előkészítése a kiválasztott base model használatára.
	 */
	public function prepare(array $model): bool;
  
  public function stop(): bool;
  /**
   * Chat request küldése a providernek.
   *
   * @param array<int, array<string, mixed>> $messages
   * @param array<string, mixed> $options
   * @return array<string, mixed>
   */
public function chat(
    array $messages,
    array $options = [],
    array $tools = []
): ProviderResponse;

  /**
   * A provider által elérhető modellek lekérése.
   *
   * @return array<string, mixed>
   */
  public function models(): array;
	
	/**
	 * A provider elérhető-e.
	 */
	public function isAvailable(): bool;	

  public function capabilities(?string $modelId = null): array;
}

?>