import 'package:autrex/constants/icons.dart';
import 'package:autrex/constants/size.dart';
import 'package:autrex/screens/home_screen.dart';
import 'package:flutter/material.dart';
import 'package:autrex/constants/color.dart';

class BaseScreen extends StatefulWidget {
  const BaseScreen({Key? key}) : super(key: key);

  @override
  _BaseScreenState createState() => _BaseScreenState();
}

class _BaseScreenState extends State<BaseScreen> {
  int _selectedIndex = 0;

  static const List<Widget> _widgetOptions = <Widget>[
    HomeScreen(),
    HomeScreen(),
    HomeScreen(),
    HomeScreen(),
  ];



  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: _widgetOptions.elementAt(_selectedIndex),
      ),
      bottomNavigationBar: BottomNavigationBar(
        type: BottomNavigationBarType.fixed,
        selectedItemColor: kPrimaryColor,
        backgroundColor: Colors.white,
        elevation: 0,
        items: [
          BottomNavigationBarItem(
            activeIcon: Image.asset(
              ic11,
              height: kBottomNavigationBarItemSize,
            ),
            icon: Image.asset(
              ic1,
              height: kBottomNavigationBarItemSize,
            ),
            label: "Home",
          ),
          BottomNavigationBarItem(
            activeIcon: Image.asset(
              ic22,
              height: kBottomNavigationBarItemSize,
            ),
            icon: Image.asset(
              ic2,
              height: kBottomNavigationBarItemSize,
            ),
            label: "Contact Us",
          ),
          BottomNavigationBarItem(
            activeIcon: Image.asset(
              ic33,
              height: kBottomNavigationBarItemSize,
            ),
            icon: Image.asset(
              ic3,
              height: kBottomNavigationBarItemSize,
            ),
            label: "Favorites",
          ),
          BottomNavigationBarItem(
            activeIcon: Image.asset(
              ic44,
              height: kBottomNavigationBarItemSize,
            ),
            icon: Image.asset(
              ic4,
              height: kBottomNavigationBarItemSize,
            ),
            label: "About Us",
          ),
        ],
        currentIndex: _selectedIndex,
          onTap: (int index) {
            setState(() {
              _selectedIndex = index;
            });
          }),
    );
  }
}
