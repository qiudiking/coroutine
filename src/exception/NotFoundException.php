<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 2019/7/8
 * Time: 17:49
 */

namespace Scar;


use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends ContainerException implements NotFoundExceptionInterface
{

}