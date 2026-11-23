<?php
class HomeController extends Controller
{
    public function indexAction(): void
    {
        $this->render('home/landing', [
            'title' => 'Campuserve: student support that keeps track',
            'routing' => Ticket::categories(),
            'user' => Auth::user(),
        ], 'layouts/public');
    }
}
