<?php

/*
 * This file is part of the ausi/slug-generator package.
 *
 * (c) Martin Auswöger <martin@auswoeger.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Ausi\SlugGenerator;

/**
 * Slug generator interface.
 *
 * @author Martin Auswöger <martin@auswoeger.com>
 */
interface SlugGeneratorInterface
{
	/**
	 * Generate a slug from the specified text.
	 *
	 * @param string   $text
	 * @param iterable $options SlugOptions object or options array
	 *
	 * @return string
	 */
	public function generate(string $text, iterable $options = []): string;
}
